<?php

// videoId指定があるか取得する
$videoId = null;
if ($argc > 1) {
	$videoId = $argv[1];
}

if (empty($videoId)) {
	echo "videoIdを指定してください。\n";
	die;
}

require_once(dirname(__FILE__) . '/../common.php');
$config = Common::GetConfig();
require_once($config->LibDir . 'nicoMove.php');
$filePath = $config->DataDir . Common::$FileNameVideos;

// 対象となるビデオデータを取得し、ダウンロード＆アップロードを実施
$info = null;
$videos = @simplexml_load_file($filePath);
if ($videos && $videos->video) {
	foreach($videos->video as $v) {
		$id = '' . $v->videoId;
		if ($id == $videoId) {
			//
			// ダウンロード＆アップロードを実施
			//
			$nm = new NicoMove();
			$mode = '' . $v->upMode;
			if ($mode == 1) {
				// Fileアップロード
				$info = $nm->Nico2File($v->videoId);
				if ($info) {
					if (!empty($config->FileUrl)) {
						$info['fileUrl'] = $config->FileUrl . basename($info['filePath']);
					}
				}
			}
			else if  ($mode == 2) {
				// Youtubeアップロード
				$info = $nm->Nico2Youtube($v->videoId, $v->isPrivate);
				if ($info) {
					$info['fileUrl'] = 'http://www.youtube.com/watch?v=' . $info['youtubeVideoId'];
				}
			}
			else {}

			break;
		}
	}
}

//
// ダウンロードが完了したので、データ更新
//

// ロック取得
if (Common::LockVideos()) {
	// ビデオリストを取得
	$videos = @simplexml_load_file($filePath);

	// ビデオデータを取得する
	$video = null;
	if ($videos && $videos->video) {

		foreach($videos->video as $v) {
			$id = '' . $v->videoId;
			if ($id == $videoId) {
				$video = $v;
				break;
			}
		}
	}

	// ビデオデータを更新する
	if ($video) {
		if ($info) {
			$video->status = 9;
			$video->title = $info['title'];
			$video->filePath = $info['filePath'];
			$video->fileUrl = $info['fileUrl'];
			if (!empty($info['youtubeVideoId'])) $video->youtubeVideoId = $info['youtubeVideoId'];
			$videos->asXml($filePath);

			echo "動画 $video->title の処理が完了しました。\n";
		}
		else {
			$video->status = 7;
			$videos->asXml($filePath);
			echo "動画 $video->videoId の処理に失敗しました。\n";
		}
	}

	// ロック解放
	Common::ReleaseVideos();
}
