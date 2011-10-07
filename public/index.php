<?php require_once(dirname(__FILE__) . '/header.php'); ?>

<?php
	// 動画リストの取得
	$videos = @simplexml_load_file($config->DataDir . Common::$FileNameVideos);
?>
		<section>
			<div id="message" style="margin:5px;">
			</div>

			<table id="videoList" class="zebra-striped">
				<!-- テーブルヘッダー -->
				<thead>
				<tr>
					<th>&nbsp;</th>
					<th>SmileID</th>
					<th>タイトル</th>
					<th>モード</th>
					<th>ステータス</th>
					<th>DL時間帯</th>
					<th>非公開</th>
					<th>&nbsp;</th>
				</tr>

				<!-- 追加データ -->
				<tr>
					<td>&nbsp;</td>
					<td><input id="newId" name="newId" size="10" placeholder="sm000000" required></td>
					<td>&nbsp;</td>
					<td><select id="newUpMode" style="width: 8em"><?php
						foreach (Common::$UpMode as $k => $v) {
							$selected = '';
							if ($k == '1') $selected = ' selected';
							echo sprintf('<option value="%s"%s>%s</option>', Common::Sanitize($k), $selected, Common::Sanitize($v));
						}
					?></select></td>
					<td>&nbsp;</td>
					<td>
						<select id="newDlTimeBegin" style="width: 6em"><?php
							for ($i = 0; $i <= 48; $i++) {
								$time = sprintf('%02d:00', $i);
								$selected = '';
								if ($i == 3) $selected = ' selected';
								echo sprintf('<option value="%s"%s>%s</option>', $time, $selected, $time);
							}
						?></select>
						～
						<select id="newDlTimeEnd" style="width: 6em"><?php
							for ($i = 0; $i <= 48; $i++) {
								$time = sprintf('%02d:00', $i);
								$selected = '';
								if ($i == 6) $selected = ' selected';
								echo sprintf('<option value="%s"%s>%s</option>', $time, $selected, $time);
							}
						?></select>
					</td>
					<td><input id="newIsPrivate" type="checkbox" value="" checked></td>
					<td><button class="btn" onclick="addVideo(); return false;">追加</td>
				</tr>
				</thead>

				<!-- 登録データ -->
				<tbody>
<?php if (!empty($videos)) : ?>
<?php foreach ($videos->video as $video) : ?>
				<tr>
					<td>
<?php $smileType = strtolower(substr($video->videoId, 0, 2)); ?>
<?php if (($smileType == 'nm') || ($smileType == 'sm')): ?>
						<img src="http://tn-skr.smilevideo.jp/smile?i=<?php echo Common::Sanitize(substr($video->videoId, 2, strlen($video->videoId) - 2)); ?>" style="width:65px; height:50px" />
<?php else: ?>
						<img style="width:65px; height:50px" />
<?php endif; ?>
					</td>
					<td><?php
						// URLが定義されている場合は、リンクにする
						if (!empty($video->fileUrl)) {
							echo sprintf('<a href="%s">%s</a>', $video->fileUrl, Common::Sanitize($video->videoId));
						}
						else {
							echo Common::Sanitize($video->videoId);
						}
					?></td>
					<td><?php echo Common::Sanitize($video->title); ?> </td>
					<td><?php echo Common::GetUpModeName($video->upMode); ?></td>
					<td><?php echo Common::GetUpStatusName($video->status); ?></td>
					<td><?php echo Common::Sanitize($video->dlTimeBegin . '～' . $video->dlTimeEnd); ?></td>
					<td><?php if (!empty($video->isPrivate)) { echo '☑'; } else { echo '&nbsp;';} ?></td>
					<td><button class="btn" onclick="return delVideo('<?php echo $video->videoId; ?>');"/>削除</td>
				</tr>
<?php endforeach; ?>
<?php endif; ?>
				</tbody>
			</table>
		</section>

		<script>
function delVideo(id) {
	$.post(
		'delVideo.php',
		{'videoId': id},
		function (data, status) {
			if (data == 1) {
				location.reload();
			}
			else {
				var errorMessage = '<div class="alert-message error">' + data + '</div>';
				$('#message').html(errorMessage);
			}
		},
		'html'
	);
}

function addVideo() {
	// 入力データの収集
	var id = $('#newId').val();
	var mode = $('#newUpMode').val();
	var dlTimeBegin = $('#newDlTimeBegin').val();
	var dlTimeEnd = $('#newDlTimeEnd').val();
	var isPrivate = $('#newIsPrivate').attr('checked');
	if (isPrivate) isPrivate = 1;
	else isPrivate = 0;

	// 入力チェック
	var errorMessage = '';
	if (id.length <= 0) {
		errorMessage += '・SimleIDはかならず入力してください';
	}
	else { }
	if (dlTimeBegin >= dlTimeEnd) {
		if (errorMessage.length > 0) errorMessage += '<br/>';
		errorMessage += '・DL時間帯は終了より開始を前にしてください';
	}

	if (errorMessage.length > 0) {
		errorMessage = '<div class="alert-message error">' + errorMessage + '</div>';
		var message = $('#message');
		message.html(errorMessage);

		return false;
	}
	else {
		$.post(
			'addVideo.php',
			{'videoId': id, 'upMode': mode, 'dlTimeBegin': dlTimeBegin, 'dlTimeEnd': dlTimeEnd, 'isPrivate': isPrivate},
			function (data, status) {
				if (data == 1) {
					// デフォルト値として保持しておく
					localStorage['defaultUpMode'] = mode;
					localStorage['defaultDlTimeBegin'] = dlTimeBegin;
					localStorage['defaultDlTimeEnd'] = dlTimeEnd;
					localStorage['defaultIsPrivate'] = isPrivate;

					location.reload();
				}
				else {
					var errorMessage = '<div class="alert-message error">' + data + '</div>';
					$('#message').html(errorMessage);
				}
			},
			'html'
		);
	}

	return true;
}

$(function()
{
	// プラグインの設定
	$("img").lazyload({ effect : "fadeIn" });
	$("#videoList").tablesorter({
		sortList: [[4,1]],
		headers: {
			0: {
				sorter: false
			},
			7: {
				sorter: false
			}
		}
	});

	// 追加時のデフォルト選択
	var mode = localStorage['defaultUpMode'];
	if (mode) {
		$('#newUpMode').val(mode);
	}

	var dlTimeBegin = localStorage['defaultDlTimeBegin'];
	if (dlTimeBegin) {
		$('#newDlTimeBegin').val(dlTimeBegin);
	}

	var dlTimeEnd = localStorage['defaultDlTimeEnd'];
	if (dlTimeEnd) {
		$('#newDlTimeEnd').val(dlTimeEnd);
	}

	var isPrivate = localStorage['defaultIsPrivate'];
	if (isPrivate === '1') {
		$('#newIsPrivate').attr('checked', true);
	}
	else if (isPrivate === '0') {
		$('#newIsPrivate').attr('checked', false);
	}
	else {}
});
		</script>

<?php require_once(dirname(__FILE__) . '/footer.php'); ?>
