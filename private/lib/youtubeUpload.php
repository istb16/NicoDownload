<?php
//
// Youtubeアップロードクラス
//
class YoutubeUpload
{
	//
	// プロパティ
	//

	// アップロードするYoutubeユーザーのメールアドレス
	public $LoginEmail = null;

	// アップロードするYoutubeユーザーのパスワード
	public $LoginPassword = null;

	// Youtube Developer Key
	// ディベロッパーキーの登録時に取得したもの
	public $YoutubeDataAPIKey = null;

	// ClientID
	// ディベロッパーキーの登録時に取得したもの
	public $ClientID = null;

	//
	// Youtubeへのアップロード
	//
	// [params]
	// 	filePath : 動画のファイルパス
	// 	title : 動画のタイトル
	// 	description : 動画の説明
	// 	category : 動画のカテゴリ
	//		※ 2011/08/30時点での有効カテゴリは以下です
	//			Film, Autos, Music, Animals, Sports, Travel, Shortmov, Videoblog, Games
	//			Comedy, People, News, Entertainment, Education, Howto, Nonprofit, Tech
	//			Movies_Anime_animation, Movies, Movies_Comedy, Movies_Documentary, Movies_Action_adventure
	//			Movies_Classics, Movies_Foreign, Movies_Horror, Movies_Drama, Movies_Family, Movies_Shorts
	//			Shows, Movies_Sci_fi_fantasy, Movies_Thriller, Trailers
	// 	keywords : 動画に関連付けるキーワード（必ず1個上必要で、複数指定する場合は、カンマ','区切りとする）
	// 	private : 非公開にする場合、true
	// [return] ビデオID、ただし失敗時はfalse
	public function Upload($filePath, $title, $description = '', $category = 'People', $keywords = 'reproduce', $private = false)
	{
		// パラメータチェック
		if (!$this->CheckProperty()) return false;
		if (empty($filePath) || empty($title) || empty($keywords)) return false;

		// 動画のメタ情報を作成
		$xml = '<?xml version="1.0"?>'
			.	'<entry xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:yt="http://gdata.youtube.com/schemas/2007">'
			.	  '<media:group>'
			.		'<media:title type="plain">'
			.			$title
			.		'</media:title>'
			.		'<media:description type="plain">'
			.			$description
			.		'</media:description>'
			.		'<media:category scheme="http://gdata.youtube.com/schemas/2007/categories.cat">'
			.			$category
			.		'</media:category>'
			.		'<media:keywords>'
			.			$keywords
			.		'</media:keywords>';
		if ($private) $xml .= '<yt:private/>';
		$xml .=   '</media:group>'
			.	'</entry>';

		//
		// ログイン処理
		//
		$token = null;
		$user = null;
		$url = parse_url('https://www.google.com/youtube/accounts/ClientLogin');
		$request = "Email=$this->LoginEmail&Passwd=$this->LoginPassword&service=youtube&source=upload";
		if ($fp = fsockopen('ssl://' . $url['host'], 443)) {
			$request = 'POST ' . $url['path'] . ' HTTP/1.1' . "\n"
				. 'Content-Type: application/x-www-form-urlencoded' . "\n"
				. 'Content-Length: ' . strlen($request) . "\n"
				. 'Connection: Close' . "\n\n"
				. $request;
			fputs($fp, $request);
			$temp = '';
			while (!feof($fp)) {
				$temp .= fgets($fp);
			}

			if (preg_match('/Auth=(.*?)\n/i', $temp, $match)) {
				$token = $match[1];
			}

			if (preg_match('/YouTubeUser=(.*?)\n/i', $temp, $match)) {
				$user = $match[1];
			}

			fclose($fp);
		}
		if (empty($token) || empty($user)) return false;

		//
		// ファイルのアップロード
		//
		$url = parse_url('http://uploads.gdata.youtube.com/feeds/api/users/' . $user . '/uploads');

		// 動画ファイルポインタを取得
		$fpUp = @fopen($filePath, 'r');
		if (!$fpUp) {
			return false;
		}

		// ファイルサイズの取得
		$fileSize = filesize($filePath);

		// ファイル名を取得
		$fileName = basename($filePath);

		// ファイル拡張子を取得
		$fileExtension = substr($fileName, strrpos($fileName, '.') + 1);
		$fileExtension = strtolower($fileExtension);

		// 動画のContent-Typeを取得
		$contentType = 'video/x-flv';
		switch($fileExtension) {
			case '3gp':
				$contentType = 'video/3gpp';
				break;
			case 'mp4':
				$contentType = 'video/mp4';
			case 'flv':
			default:
				break;
		}

		// 送信データの作成
		$contentHeader = '--END_OF_PART' . "\n"
			. 'Content-Type: application/atom+xml; charset=UTF-8' . "\n\n"
			. $xml . "\n"
			. '--END_OF_PART' . "\n"
			. 'Content-Type: ' . $contentType . "\n"
			. 'Content-Transfer-Encoding: binary'. "\n\n";
		$contentFooter = "\n"
			. '--END_OF_PART--' . "\n";
		// アップロード
		$receive = '';
		if ($fp = fsockopen($url['host'], 80)) {
			// ヘッダーの送信
			$Header = 'POST ' . $url['path'] . ' HTTP/1.1' . "\n"
				. 'Host: ' . $url['host'] . "\n"
				. 'Authorization: GoogleLogin auth=' . $token . "\n"
				. 'GData-Version: 2' . "\n"
				. 'X-GData-Client: ' . $this->ClientID . "\n"
				. 'X-GData-Key: key=' . $this->YoutubeDataAPIKey . "\n"
				. 'Slug: ' . $fileName . "\n"
				. 'Content-Type: multipart/related; boundary="END_OF_PART"' . "\n"
				. 'Content-Length: ' . (strlen($contentHeader) + strlen($contentFooter ) + $fileSize) . "\n"
				. 'Connection: close' . "\n\n";
			fputs($fp, $Header);

			// コンテンツ（ヘッダー）の送信
			fputs($fp, $contentHeader);

			// コンテンツ（データ）の送信
			while (!feof($fpUp)) {
				fputs($fp, fread($fpUp, 1024));
			}

			// コンテンツ（フッダー）の送信
			fputs($fp, $contentFooter);

			// 受信データを読み込む
			while (!feof($fp)) {
				$receive .= fgets($fp);
			}

			fclose($fp);
		}
		fclose($fpUp);

		// 受信データを解析
		if (empty($receive)) {
			return false;
		}
		else {
			// 解析して、動画のビデオIDを返却
			$xml = substr($receive, strpos($receive, '<?xml'));
			$xml = @simplexml_load_string($xml);
			if ($xml && $xml->id) {
				$posKey = 'video:';
				$posSt = strpos($xml->id, $posKey);
				if ($posSt !== false) {
					$posSt += strlen($posKey);
					$posEd = strpos($xml->id, ',', $posSt);
					if ($posEd === false) $posEd = strlen($xml->id);
					$videoId = substr($xml->id, $posSt, $posEd - $posSt);
					return $videoId;
				}
			}
			return false;
		}
	}


	//
	// Youtubeから動画を削除
	//
	// [params]
	// 	videoId : Youtube動画ID
	// [return] 成功時はtrue、失敗時はfalse
	public function Delete($videoId)
	{
		// パラメータチェック
		if (!$this->CheckProperty()) return false;
		if (empty($videoId)) return false;

		//
		// ログイン処理
		//
		$token = null;
		$user = null;
		$url = parse_url('https://www.google.com/youtube/accounts/ClientLogin');
		$request = "Email=$this->LoginEmail&Passwd=$this->LoginPassword&service=youtube&source=upload";
		if ($fp = fsockopen('ssl://' . $url['host'], 443)) {
			$request = 'POST ' . $url['path'] . ' HTTP/1.1' . "\n"
				. 'Content-Type: application/x-www-form-urlencoded' . "\n"
				. 'Content-Length: ' . strlen($request) . "\n"
				. 'Connection: Close' . "\n\n"
				. $request;
			fputs($fp, $request);
			$temp = '';
			while (!feof($fp)) {
				$temp .= fgets($fp);
			}

			if (preg_match('/Auth=(.*?)\n/i', $temp, $match)) {
				$token = $match[1];
			}

			if (preg_match('/YouTubeUser=(.*?)\n/i', $temp, $match)) {
				$user = $match[1];
			}

			fclose($fp);
		}
		if (empty($token) || empty($user)) return false;

		//
		// ファイルの削除
		//
		$url = parse_url('http://gdata.youtube.com/feeds/api/users/'. $user . '/uploads/' . $videoId);

		// 送信データの作成
		$receive = '';
		if ($fp = fsockopen($url['host'], 80)) {
			// ヘッダーの送信
			$Header = 'DELETE ' . $url['path'] . ' HTTP/1.1' . "\n"
				. 'Host: ' . $url['host'] . "\n"
				. 'Content-Type: application/atom+xml' . "\n"
				. 'Authorization: GoogleLogin auth="' . $token . '"' . "\n"
				. 'GData-Version: 2' . "\n"
				. 'X-GData-Client: ' . $this->ClientID . "\n"
				. 'X-GData-Key: key=' . $this->YoutubeDataAPIKey . "\n"
				. 'Connection: Close' . "\n\n";
			fputs($fp, $Header);

			// 受信データを読み込む
			while (!feof($fp)) {
				$receive .= fgets($fp);
			}

			fclose($fp);
		}

		// 受信データを解析
		if (empty($receive)) {
			return false;
		}
		else {
			return true;
		}
	}

	//
	// プロパティチェック
	//
	private function CheckProperty()
	{
		if (empty($this->LoginEmail)) return false;
		if (empty($this->LoginPassword)) return false;
		if (empty($this->YoutubeDataAPIKey)) return false;
		if (empty($this->ClientID)) return false;

		return true;
	}
}