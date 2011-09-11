<?php
	require_once(dirname(__FILE__) . '/common.php');
	require_once($config->LibDir . 'youtubeUpload.php');

	// パラメータを取得
	$videoId = $_POST['videoId'];
	if (empty($videoId)) {
		echo '削除に失敗しました。リロード後、もう一度お試しください。';
	}
	else {
		// ロック取得
		if (!Common::LockVideos()) {
			echo '削除に失敗しました。リロード後、もう一度お試しください。';
		}
		else {
			// 現在のデータを取得
			$filePath = $config->DataDir . Common::$FileNameVideos;
			$videos = @simplexml_load_file($filePath);

			// データから指定された項目を削除
			$delVideo = null;
			for ($key = 0; $key < count($videos->video); $key++) {

				$id = '' . $videos->video[$key]->videoId;
				if ($id == $videoId) {
					$delVideo['upMode'] = (string)$videos->video[$key]->upMode;
					$delVideo['filePath'] = (string)$videos->video[$key]->filePath;
					$delVideo['youtubeVideoId'] = (string)$videos->video[$key]->youtubeVideoId;
					unset($videos->video[$key]);
					break;
				}
			}

			// 保存
			$videos->asXml($filePath);

			// ロック解放
			Common::ReleaseVideos();

			if ($delVideo) {
				if ($delVideo['upMode'] == 1) {
					// ファイルの削除
					if (file_exists($delVideo['filePath'])) unlink($delVideo['filePath']);
				}
				else if ($delVideo['upMode'] == 2) {
					// Youtubeファイルの削除
					$yu = new YoutubeUpload();
					$yu->LoginEmail = $config->YoutubeLoginEmail;
					$yu->LoginPassword = $config->YoutubeLoginPassword;
					$yu->YoutubeDataAPIKey = $config->YoutubeDevDataAPIKey;
					$yu->ClientID = $config->YoutubeDevClientId;
					$rtUpload = $yu->Delete($delVideo['youtubeVideoId']);
				}
				else {}
			}

			//
			echo 1;
		}
	}