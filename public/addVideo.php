<?php
	require_once(dirname(__FILE__) . '/common.php');

	// パラメータを取得
	$videoId = $_POST['videoId'];
	$upMode = $_POST['upMode'];
	$dlTimeBegin = $_POST['dlTimeBegin'];
	$dlTimeEnd = $_POST['dlTimeEnd'];
	$isPrivate = $_POST['isPrivate'];
	if (empty($videoId) || empty($upMode) || empty($dlTimeBegin) || empty($dlTimeEnd)) {
		echo '追加に失敗しました。リロード後、もう一度お試しください。';
	}
	else if ($dlTimeBegin >= $dlTimeEnd) {
		echo 'DL時間帯が不正です。リロード後、もう一度お試しください。';
	}
	else if (preg_match('/[^0-9a-zA-Z]/u', $videoId)) {
		echo 'SmileIDが不正です。もう一度お試しください。';
	}
	else {
		// ロック取得
		if (!Common::LockVideos()) {
			echo '追加に失敗しました。リロード後、もう一度お試しください。';
		} else {
			// 現在のデータを取得
			$filePath = $config->DataDir . Common::$FileNameVideos;
			$videos = @simplexml_load_file($filePath);
			if (!$videos) $videos = new SimpleXMLElement('<videos></videos>');

			// すでに存在しているかチェックする
			$isExist = false;
			foreach ($videos->video as $video) {
				$id = '' . $video->videoId;
				if ($id == $videoId) {
					$isExist = true;
					break;
				}
			}
			if ($isExist) {
				echo 'すでに登録されています。';
			}
			else {
				// データとして追加
				$video = $videos->addChild('video');
		 		$video->addChild('videoId', $videoId);
		 		$video->addChild('upMode', $upMode);
				$video->addChild('status', 1);
		 		$video->addChild('dlTimeBegin', $dlTimeBegin);
		 		$video->addChild('dlTimeEnd', $dlTimeEnd);
		 		$video->addChild('isPrivate', $isPrivate);

				// 保存
				$videos->asXml($filePath);

				echo 1;
			}

			// ロック解放
			Common::ReleaseVideos();
		}
	}