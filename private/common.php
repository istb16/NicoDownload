<?php
// TODO: リスナーでごみになったロックを検出して、処理しましょう
// TODO: リスナーで無駄なファイルを削除するようにしましょう。
// TODO: 削除時にYoutubeだったら、そっちも削除するようにできるか。
// TODO: nicoDownload.phpにて動画リストのテスト中

// config.xmlのファイルパス（環境に応じて変更してください）
define('CONFIG_PATH', dirname(__FILE__) . '/data/config.xml');

class Common
{
	// config.xmlのファイルパス
	private static $configPath = null;

	// 設定データ
	private static $config = null;

	// アップロードモード
	public static $UpMode = array(
		'1' => 'ファイル',
		'2' => 'Youtube',
	);

	// アップロードステータス
	public static $UpStatus = array(
		'1' => '未済',
		'2' => '処理中',
		'7' => 'エラー',
		'9' => '完了',
	);

	// ビデオリストファイル名
	public static $FileNameVideos = 'videos.xml';

	// ビデオリストファイルのロック要ディレクトリ名
	public static $LockVideosDir = 'videos.xml.lock';

	// リスナーのロック要ディレクトリ名
	public static $LockLisnerDir = "lisner.lock";

	//
	// config.xmlのファイルパス取得
	//
	// [return]ファイルパス
	public static function GetConfigPath()
	{
		if (empty(Common::$configPath)) Common::$configPath = Common::convPath(CONFIG_PATH);
		return Common::$configPath;
	}

	//
	// 設定データの取得
	//
	// [return]設定データ
	public static function GetConfig()
	{
		if (empty(Common::$config)) {
			$config = @simplexml_load_file(Common::GetConfigPath());
			if ($config) {
				// ファイルパスを絶対パスに変換する
				$config->DataDir = Common::convPath(dirname(__FILE__) . '/' . $config->DataDir) . '/';
				$config->LibDir = Common::convPath(dirname(__FILE__) . '/' . $config->LibDir) . '/';
				$config->CmdDir = Common::convPath(dirname(__FILE__) . '/' . $config->CmdDir) . '/';
				$config->WorkDir = Common::convPath(dirname(__FILE__) . '/' . $config->WorkDir) . '/';
				$config->FileDir = Common::convPath(dirname(__FILE__) . '/' . $config->FileDir) . '/';
			}
			else {
				echo "Can not read config.\n";
				die;
			}

			Common::$config = $config;
		}
		return Common::$config;
	}

	//
	// サニタイズ処理
	//
	// [params]
	// 	str : サニタイズしたい文字列
	// [return] サニタイズ後の文字列
	public static function Sanitize($str)
	{
		return htmlspecialchars($str, ENT_QUOTES);
	}

	//
	// アップロードモードの名称取得
	//
	// [params]
	//	upMode : アップロードモード
	// [return] アップロードモードの名称
	public static function GetUpModeName($upMode)
	{
		foreach (Common::$UpMode as $k => $v) {
			if ($k == $upMode) return Common::Sanitize($v);
		}
		return false;
	}

	//
	// アップロードステータスの名称取得
	//
	// [params]
	//	upStatus : アップロードステータス
	// [return] アップロードステータスの名称
	public static function GetUpStatusName($upStatus)
	{
		foreach (Common::$UpStatus as $k => $v) {
			if ($k == $upStatus) return Common::Sanitize($v);
		}
		return false;
	}

	//
	// videos.xmlのロック取得
	//
	// [return] 成否
	public static function LockVideos()
	{
		$dirPath = Common::$config->WorkDir . Common::$LockVideosDir;
		return @mkdir($dirPath);
	}

	//
	// videos.xmlのロック解放
	//
	public static function ReleaseVideos()
	{
		$dirPath = Common::$config->WorkDir . Common::$LockVideosDir;
		@rmdir($dirPath);
	}


	//
	// Lisner(ダウンロード＆アップロード開始検知)のロック取得
	//
	// [return] 成否
	public static function LockLisner()
	{
		$dirPath = Common::$config->WorkDir . Common::$LockLisnerDir;
		return @mkdir($dirPath);
	}

	//
	// Lisner(ダウンロード＆アップロード開始検知)のロック解放
	//
	public static function ReleaseLisner()
	{
		$dirPath = Common::$config->WorkDir . Common::$LockLisnerDir;
		@rmdir($dirPath);
	}

	//
	// 絶対パスへの変換
	//
	// [params]
	// 	path : ファイルパス（相対パスか絶対パス）
	// [return] 絶対パス
	private static function convPath($path)
	{
		$rtValue = realpath($path);
		if ($rtValue === false) $rtValue = $path;
		return $rtValue;
	}
}