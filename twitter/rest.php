<?php
namespace Twitter;

require_once dirname(__FILE__).'/common.php';

class Rest extends Common {

	private static $self;

	public static function getInstance($conf_nm = '', $renew = false){
		if(is_null(self::$self) || $renew){
			self::$self = new self($conf_nm);
		}
		return self::$self;
	}


	/**
	 * ブロックしているユーザーを無差別に解除する
	 * ※デフォルトは1000件
	 *
	 * @param object $user_id ブロック中ユーザー一覧のオブジェクト
	 * @param integer $unblock_count 一度にブロック解除する人数
	 */
	public function unblock($user_id, $unblock_count = 1000){
		$i = 0;
		$ids = $this->get_user_ids_from_statuses($user_id);

		foreach($ids as $id){
			$result = $this->post_blocks_destroy($id);

			if($result){
				$i++;
			}
			if($i == $unblock_count){
				break;
			}
		}
	}


	/**
	 * configファイルで指定したキーワードの含まれるツイートを検索してフォローする
	 */
	public function search_and_follow(){
		$response = $this->search_tweets(
			$this->config['follow_keyword_today'],
			$this->config['search_lang'],
			$this->config['follow_search_count']
		);
		if($response){
			$statuses = $response->statuses;
			foreach($statuses as $status){
				if($status->user->following == false){
					$this->follow($status->user->id_str);
				}
			}
		}
	}

	/**
	 * configファイルで指定したキーワードの含まれるツイートを検索してお気に入りする
	 * 一度お気に入りしたユーザーはconfigで指定した期間再度お気に入りしない
	 */
	public function search_and_favorite(){
		$fav_history_list = $black_list = array();
		$fav_history_file = fopen($this->FAVORITE_HISTORY_FILE_PATH, 'a+');

		if($fav_history_file){
			while(!feof($fav_history_file)){
				$fav_history_list = fgetcsv($fav_history_file) ?: $fav_history_list;
				$fav_history_list = array_filter($fav_history_list);
			}
		}

		foreach($fav_history_list as $val){
			list($user_id, $date) = explode(SS, $val);
			if(strtotime($date) >= strtotime(date(DATE_FORMAT, strtotime('-'.$this->config['day_to_fav_same_user_again'].' day')))){
				$black_list[$user_id] = $date;
			}
		}

		$response = $this->search_tweets(
			$this->config['fav_keyword_today'],
			$this->config['search_lang'],
			$this->config['fav_search_count']
		);
		if($response){
			$statuses = $response->statuses;
			foreach($statuses as $status){
				if($status->favorited == false && !(array_key_exists($status->user->id_str, $black_list))){
					$this->post_favorite_create($status->id_str);
					$black_list[$status->user->id_str] = date(DATE_FORMAT);
				}
			}
		}

		\Twitter\Util::file_truncate($fav_history_file);
		foreach($black_list as $user_id => $date){
			fwrite($fav_history_file, $user_id.SS.$date.',');
		}
		fclose($fav_history_file);
	}

	/**
	 * フォロー限度に応じてフォローする
	 *
	 * @return boolean フォローが成功したか
	 */
	public function follow($user_id){
		$fr_cnt_day = 0;
		$fr_cnt_date = date(DATE_FORMAT);
		$fr_cnt_day_file_path = $this->FRIEND_REQUEST_COUNT_DAY_FILE_PATH;
		$fr_history_file_path = $this->FRIEND_REQUEST_HISTORY_FILE_PATH;
		$result = false;

		$fr_cnt_day_file = fopen($fr_cnt_day_file_path, 'a+');

		//すでに本日フレンド申請した数を獲得する
		if(\Twitter\Util::get_file_size($fr_cnt_day_file)){ //filesizeではキャッシュ(statcache)を参照してしまうため、このように対策
			if($fr_cnt_info = fgets($fr_cnt_day_file)){
				$fr_cnt_info = rtrim($fr_cnt_info);
				list($fr_cnt_day, $fr_cnt_date) = explode(SS, $fr_cnt_info);
			}

			//ファイル生成から1日立っていた場合は、フォローカウントをリセットする
			if(strtotime($fr_cnt_date) < strtotime(date(DATE_FORMAT,strtotime('-1 day')))){
				\Twitter\Util::file_truncate($fr_cnt_day_file);
			}
		}

		if($this->friend_cnt < $this->limit['max'] && ($fr_cnt_day < $this->limit['limit_per_day'])){
			//フレンド申請する
			if($this->post_friendships_create($user_id)){
				$fr_cnt_day ++;
				$this->friend_cnt ++;

				//フレンド申請リストファイルを更新する
				$friend_req_file_file = fopen($fr_history_file_path, 'a+');
				fwrite($friend_req_file_file, $user_id.SS.date(DATE_FORMAT).',');
				fclose($friend_req_file_file);

				//本日フレンド申請した人数を書き出す
				\Twitter\Util::file_truncate($fr_cnt_day_file);
				fwrite($fr_cnt_day_file, $fr_cnt_day.SS.date(DATE_FORMAT));

				$result = true;
			}
		}

		fclose($fr_cnt_day_file);

		return $result;
	}

	/**
	 * configファイルで設定した期間フォロー返ししてくれなかった人をフォロー解除する
	 */
	public function auto_unfollow(){
		$friend_req_list = $friends_remain = array();
		$friend_req_file = fopen($this->FRIEND_REQUEST_HISTORY_FILE_PATH, 'a+');

		if($friend_req_file){
			while(!feof($friend_req_file)){
				$friend_req_list = fgetcsv($friend_req_file) ?: $friend_req_list;
				$friend_req_list = array_filter($friend_req_list);
			}
		}
		if($friend_req_list){
			//フォロワーのidリストを取得
			$follower_id_list = array();
			$follower_id_list = $this->get_followers_ids($this->config['user_screen_name']);

			//configで指定した期間以上フォロー返ししていない人をアンフォロー
			foreach($friend_req_list as $friend_req){
				list($user_id, $date) = explode(SS, $friend_req);
				if(!(in_array($user_id, $follower_id_list))){
					if(strtotime($date) <= strtotime(date(DATE_FORMAT, strtotime('-'.$this->config['day_to_unfollow'].' day')))){
						$this->post_friendships_destroy($user_id);
					}else{
						$friends_remain[$user_id] = $date;
					}
				}
			}
		}
		//次回判定まで持ち越すアカウントのリストを記録する
		\Twitter\Util::file_truncate($friend_req_file);
		foreach($friends_remain as $user_id => $date){
			fwrite($friend_req_file, $user_id.SS.$date.',');
		}
		fclose ($friend_req_file);
	}
}