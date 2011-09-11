<?php

// videoId指定があるか取得する
$videoId = null;
if ($argc > 1) {
	$videoId = $argv[1];
}

// 設定データを取得
require_once(dirname(__FILE__) . '/../common.php');
$config = Common::GetConfig();

// Lisnerのロック取得
if (Common::LockLisner()) {
	// ビデオ毎の処理
	lisnerVideo($config, $videoId);

	// リスナーのロック解放
	Common::ReleaseLisner();
}

function lisnerVideo($config, $videoId)
{
	// ビデオリストのロック取得
	if (Common::LockVideos()) {
		// ビデオリストを取得
		$filePath = $config->DataDir . Common::$FileNameVideos;
		$videos = @simplexml_load_file($filePath);

		if ($videos && $videos->video) {
			// 処理を開始するビデオデータを見つける
			$video = null;
			foreach($videos->video as $v) {
				// videoIdが指定され、一致している場合は無視
				$id = '' . $v->videoId;
				if ($videoId && ($id != $videoId)) continue;
				// ステータスが未済以外は無視
				if (('' . $v->status) != 1) continue;
				// videoIdが指定されていない時、DL時間帯でないものは無視
				if (!$videoId) {
					// 開始時刻の取得
					$begin = ''. $v->dlTimeBegin;
					$split = split(':', $begin);
					if (count($split) < 2) {
						// フォーマット不正
						$begin = '00:00';
						$split = array('00', '00');
					}
					$beginHour = intval($split[0]);
					$beginMin = intval($split[1]);

					// 終了時刻の取得
					$end = ''. $v->dlTimeEnd;
					$split = split(':', $end);
					if (count($split) < 2) {
						// フォーマット不正
						$end = '00:00';
						$split = array('00', '00');
					}
					$endHour = intval($split[0]);
					$endMin = intval($split[1]);
					if (((($endHour * 60) + $endMin) - (($beginHour * 60) + $beginMin)) < (24 * 60)) {
						// 1日未満の期間であれば、時間帯チェックを続ける
						if ($beginHour >= 24) $beginHour -= 24;
						if ($endHour >= 24) $endHour -= 24;
						$begin = sprintf('%02d:%02d', $beginHour, $beginMin);
						$end = sprintf('%02d:%02d', $endHour, $endMin);

						// 現在の時刻を取得
						$cur = date('H:i', time());

						// 時間帯内にいるのか判定
						if ($begin < $end) {
							if (($begin > $cur) || ($cur > $end)) continue;
						}
						else {
							if (($end > $cur) || ($cur > $begin)) continue;
						}
					}
				}

				// 対象とするビデオデータを発見
				$video = $v;
				break;
			}

			// 対象となるビデオデータが見つかったら、処理中にステータスを変更する
			if ($video) {
				echo "処理対象となる動画 [$video->videoId] が見つかりましたので、ダウンロード処理を実行します。\n";

				$video->status = 2;
				$videos->asXml($filePath);

				// 処理を開始させる
				$command = $config->PhpPath . ' ' . dirname(__FILE__) . '/download.php ' . $video->videoId . ' > /dev/null &';
				exec($command);
			}
		}

		// ビデオリストのロック解放
		Common::ReleaseVideos();
	}
}