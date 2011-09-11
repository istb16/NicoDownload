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
			// 説明にタグが入っているので、変換する
			$rtDl['description'] = preg_replace('/<br[[:space:]]*\/?[[:space:]]*>/i', "\n", $rtDl['description']);
			$rtDl['description'] = strip_tags($rtDl['description']);

			// 説明に、ニコニコ動画からの転載である旨を追記
			$rtDl['description'] = 'Original video: http://www.nicovideo.jp/watch/' . $videoId . "\n"
				. "----------------------\n"
				. $rtDl['description'];

			// Youtubeへアップロード
			$yu = new YoutubeUpload();

			$yu->LoginEmail = $this->config->YoutubeLoginEmail;
			$yu->LoginPassword = $this->config->YoutubeLoginPassword;
			$yu->YoutubeDataAPIKey = $this->config->YoutubeDevDataAPIKey;
			$yu->ClientID = $this->config->YoutubeDevClientId;

			$rtUpload = $yu->Upload(
				$rtDl['filePath'],
				$rtDl['title'],
				$rtDl['description'],
				$this->convNicoTag2YoutubeCat($rtDl['tags']),
				$this->convNicoTag2YoutubeKeywd($rtDl['tags']),
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
	// ニコニコ動画をYoutubeへ転載
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
}