<?php
require_once(dirname(__FILE__) . '/../common.php');
require_once(dirname(__FILE__) . '/nicoDownload.php');
require_once(dirname(__FILE__) . '/youtubeUpload.php');

//
// ニコニコ動画転載クラス
//
class NicoMove
{
	// 設定データ
	private $config = null;

	// ニコニコ動画とYoutubeのカテゴリ対応
	private static $catNico2Youtube = array(
		'音楽' => 'Music',
		'エンターテイメント' => 'Entertainment',
		'スポーツ' => 'Sports',
		'動物' => 'Animals',
		'料理' => 'Howto',
		'日記' => 'Videoblog',
		'自然' => 'Education',
		'科学' => 'Education',
		'歴史' => 'Education',
		'ラジオ' => 'Videoblog',
		'ニコニコ動画講座' => 'Howto',
		'政治' => 'News',
		'歌ってみた' => 'Music',
		'演奏してみた' => 'Music',
		'踊ってみた' => 'Entertainment',
		'描いてみた' => 'Entertainment',
		'ニコニコ技術部' => 'Tech',
		'アニメ' => 'Movies_Anime_animation',
		'ゲーム' => 'Games',
		'アイドルマスター' => 'Entertainment',
		'東方' => 'Entertainment',
		'VOCALOID' => 'Entertainment',
		'例のアレ' => 'Comedy',
		'その他' => 'People',
		'R-18' => 'People',
	);

	//
	// コンストラクタ
	//
	function __construct()
	{
		$this->config = Common::GetConfig();
	}

	//
	// ニコニコ動画をYoutubeへ転載
	//
	// [params]
	// 	videoId : 動画ID
	//	isPrivate : 非公開か
	// [return] 動画情報、ただし失敗時はfalse
	// 	title : タイトル
	// 	youtubeVideoId : Youtube動画ID
	public function Nico2Youtube($videoId, $isPrivate = false)
	{
		// ニコニコからダウンロード
		$nd = new NicoDownload();

		$nd->LoginEmail = $this->config->NicoLoginEmail;
		$nd->LoginPassword = $this->config->NicoLoginPassword;
		$nd->WorkDir = $this->config->WorkDir;
		$nd->DownloadDir = $this->config->WorkDir;

		$rtDl = $nd->Download($videoId);

		// ダウンロードしたファイルをYoutubeへアップロード
		if ($rtDl) {
			// ニコニコ用の動画情報から、Youtube用の動画情報へアップロードに失敗しない為に変換する
			$youtubeParam = $this->convNico2YoutubeInfo($rtDl, $videoId);

			// Youtubeへアップロード
			$yu = new YoutubeUpload();

			$yu->LoginEmail = $this->config->YoutubeLoginEmail;
			$yu->LoginPassword = $this->config->YoutubeLoginPassword;
			$yu->YoutubeDataAPIKey = $this->config->YoutubeDevDataAPIKey;
			$yu->ClientID = $this->config->YoutubeDevClientId;

			$rtUpload = $yu->Upload(
				$youtubeParam['filePath'],
				$youtubeParam['title'],
				$youtubeParam['description'],
				$youtubeParam['category'],
				$youtubeParam['tags'],
				$isPrivate
			);

			if (file_exists($rtDl['filePath'])) unlink($rtDl['filePath']);
			if (!$rtUpload) {
				$rtDl = false;
			}
			else {
				$rtDl['youtubeVideoId'] = $rtUpload;
			}
		}

		return $rtDl;
	}

	//
	// ニコニコ動画をファイルに保存
	//
	// [params]
	// 	videoId : 動画ID
	// [return] 動画情報、ただし失敗時はfalse
	// 	filePath : ファイルパス
	// 	title : タイトル
	public function Nico2File($videoId)
	{
		// ニコニコからダウンロード
		// ダウンロードしたファイルは、指定されたフォルダに配置
		$nd = new NicoDownload();

		$nd->LoginEmail = $this->config->NicoLoginEmail;
		$nd->LoginPassword = $this->config->NicoLoginPassword;
		$nd->WorkDir = $this->config->WorkDir;
		$nd->DownloadDir = $this->config->FileDir;

		$rtValue = $nd->Download($videoId);

		return $rtValue;
	}

	//
	// ニコニコ動画のタグからYoutubeのカテゴリを作成
	//
	// [params]
	// 	tags : タグ
	// [return] カテゴリ
	private function convNicoTag2YoutubeCat($tags)
	{
		// タグから対応するカテゴリを検索する
		// 対応するものが見つからない場合は、デフォルトとしてPeopleを定義
		$category = null;
		foreach ($tags as $tag) {
			if (array_key_exists($tag, NicoMove::$catNico2Youtube)) {
				$category = NicoMove::$catNico2Youtube[$tag];
				break;
			}
		}

		if (empty($category)) $category = 'People';

		return $category;
	}

	//
	// ニコニコ動画のタグからYoutubeのキーワードを作成
	//
	// [params]
	// 	tags : タグ
	// [return] キーワード
	private function convNicoTag2YoutubeKeywd($tags)
	{
		if (empty($tags)) $tags[] = 'ニコニコ動画';
		return implode(',', $tags);
	}
	
	//
	// Youtubeへアップロードできる情報になるように調整
	//
	// [params]
	// 	data : 動画情報
	// 		title : タイトル
	// 		description : 説明
	// 		tags : キーワード
	// 	videoId : 動画ID
	// [returns] 調整後の動画情報
	//		title : タイトル（最大60文字）
	//		description : 説明（タグの変換、ニコニコ転載明記、最大5000文字）
	//		tags : キーワード
	//		category : カテゴリ
	private function convNico2YoutubeInfo($data, $videoId)
	{
		// タイトルが60文字を超えていたら間引く
		$data['title'] = mb_strimwidth($data['title'], 0, 60, '...', 'UTF-8');
		
		// 説明にタグが入っているので、変換する
		$data['description'] = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/i', "\n", $data['description']);
		$data['description'] = strip_tags($data['description']);
		
		// 説明に、ニコニコ動画からの転載である旨を追記
		$data['description'] = 'Original video: http://www.nicovideo.jp/watch/' . $videoId . "\n"
			. "----------------------\n"
			. $data['description'];
			
		// 説明が5000文字を超えていたら間引く
		$data['description'] = mb_strimwidth($data['description'], 0, 5000, '...', 'UTF-8');
		
		// タグからカテゴリの作成
		$data['category'] = $this->convNicoTag2YoutubeCat($data['tags']);
		
		// それぞれのタグでカンマ , を使っていたら変換する
		foreach ($data['tags'] as $key => $tag) {
			if (strpos($tag, ',') !== false) {
				$data['tags'][$key] = str_replace(',', '，', $tag);
			}
		}
		
		// それぞれのタグが25文字を超えていたら間引く
		foreach ($data['tags'] as $key => $tag) {
			$data['tags'][$key] = mb_strimwidth($tag, 0, 25, '...', 'UTF-8');
		}
		
		// それぞれのタグが1文字以下なら除外する
		$tags = array();
		foreach ($data['tags'] as $tag) {
			if (mb_strlen($tag, 'UTF-8') > 1) $tags[] = $tag;
		}
		
		// タグからキーワードを作成する
		$data['tags'] = $this->convNicoTag2YoutubeKeywd($tags);
		
		return $data;
	}
}
