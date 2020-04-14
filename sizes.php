<?php

ini_set('display_errors', 'on');

define('COLSPAN', 10);

function mysqli_result($result, $row, $field = 0)
{
	$result->data_seek($row);
	$data = $result->fetch_array();
	return $data[$field];
}

function echo_disk_row($name, $bytes, $total, $colspan, $extra = false)
{
	echo '<tr><td colspan=\''.htmlentities($colspan, ENT_QUOTES).'\'><table style=\'width: 100%;\'><tr>'.PHP_EOL;
	echo '<td class=\'l_td\' style=\'border: 0px; padding: 0px; width: 10%;\'>'.htmlentities($name, ENT_QUOTES).'</td>'.PHP_EOL;
	echo '<td class=\'r_td\' style=\'border: 0px; padding: 0px; width: 10%;\'>'.htmlentities(get_labelled_size($bytes), ENT_QUOTES).'</td>'.PHP_EOL;
	echo '<td class=\'r_td\' style=\'border: 0px; padding: 0px; width: 10%;\'>'.htmlentities(sprintf('%.2f', (100.0 / $total) * $bytes), ENT_QUOTES).'%</td>'.PHP_EOL;
	echo '<td class=\'r_td\' style=\'border: 0px; padding: 0px; width: 10%\'>&nbsp;</td>'.PHP_EOL;
	echo '<td class=\'l_td\' style=\'border: 0px; padding: 0px;\'>'.(empty($extra) ? '&nbsp;' : htmlentities($extra, ENT_QUOTES)).'</td>'.PHP_EOL;
	echo '</tr></table></td></tr>'.PHP_EOL;
}

function get_bit_rate_average()
{
	$mysql = mysqli_connect('localhost', 'mythtv', 'mythtv', 'mythconverg');
	$result = mysqli_query($mysql,
		'SELECT SUM(filesize) * 8 / SUM(((UNIX_TIMESTAMP(endtime) - UNIX_TIMESTAMP(starttime)))) '.
		'FROM recorded WHERE (UNIX_TIMESTAMP(endtime) - UNIX_TIMESTAMP(starttime)) > 300;');
	$average = mysqli_result($result, 0);
	mysqli_close($mysql);

	return $average;
}

function get_bit_rate_maximum()
{
	$mysql = mysqli_connect('localhost', 'mythtv', 'mythtv', 'mythconverg');
	$result = mysqli_query($mysql,
		'SELECT MAX(filesize * 8 / (UNIX_TIMESTAMP(endtime) - UNIX_TIMESTAMP(starttime))) '.
		'FROM recorded WHERE (UNIX_TIMESTAMP(endtime) - UNIX_TIMESTAMP(starttime)) > 300;');
	$maximum = mysqli_result($result, 0);
	mysqli_close($mysql);

	return $maximum;
}

function get_bit_rate_text($free, $bps, $direction, $type)
{
	$mins = $free/(($bps/8.0)*60.0);
	$hours = $mins/60.0;

	if ($hours > 3)
	{
		$str = sprintf('%d', $hours).' hours';
	}
	else if ($mins > 90)
	{
		$str = sprintf('%d', $hours).' hours and '.sprintf('%d', $mins%60).' minutes';
	}
	else
	{
		$str = sprintf('%d', $mins).' minutes';
	}

	return $str.' '.$direction.', using the '.$type.' rate of '.sprintf('%d', $bps/1024.0).' Kb/sec';
}

function get_date_time($date_time)
{
	return
		substr($date_time, 11, 2).':'.
		substr($date_time, 14, 2).' '.
		substr($date_time,  8, 2).'/'.
		substr($date_time,  5, 2).'/'.
		substr($date_time,  2, 2);
}

function get_length($length)
{
	$hours = floor($length / 3600);
	$mins = ($length - ($hours * 3600)) / 60;
	return $hours.':'.sprintf('%02d', $mins);
}

function get_seconds_total()
{
	$mysql = mysqli_connect('localhost', 'mythtv', 'mythtv', 'mythconverg');
	$result = mysqli_query($mysql,
		'SELECT SUM(((UNIX_TIMESTAMP(endtime) - UNIX_TIMESTAMP(starttime)))) '.
		'FROM recorded WHERE (UNIX_TIMESTAMP(endtime) - UNIX_TIMESTAMP(starttime)) > 300;');
	$average = mysqli_result($result, 0);
	mysqli_close($mysql);

	return $average;
}

function get_recordings($order_by, $order_direction)
{
	$order = str_replace(',', ' '.$order_direction.',', $order_by).' '.$order_direction;

	$mysql = mysqli_connect('localhost', 'mythtv', 'mythtv', 'mythconverg');
	$result = mysqli_query($mysql,
		'SELECT *,'.
		' filesize * 8 / (UNIX_TIMESTAMP(endtime) - UNIX_TIMESTAMP(starttime)) bitrate,'.
		' UNIX_TIMESTAMP(progend) - UNIX_TIMESTAMP(progstart) proglength,'.
		' CONVERT_TZ(progend, \'UTC\', \'SYSTEM\') progend_system,'.
		' CONVERT_TZ(progstart, \'UTC\', \'SYSTEM\') progstart_system'.
		' FROM recorded'.
		' LEFT JOIN channel ON recorded.chanid = channel.chanid'.
		' ORDER BY recgroup, '.mysqli_real_escape_string($mysql, $order).';');
	$recordings = array();
	while ($recording = mysqli_fetch_assoc($result))
	{
		$recordings[] = $recording;
	}
	mysqli_close($mysql);

	return $recordings;
}

function get_recordings_grouped()
{
	$mysql = mysqli_connect('localhost', 'mythtv', 'mythtv', 'mythconverg');
	$result = mysqli_query($mysql,
		'SELECT title, recgroup, COUNT(*) count, SUM(filesize) filesize'.
		' FROM recorded'.
		' GROUP by title, recgroup'.
		' ORDER BY SUM(filesize) DESC;');
	$recordings = array();
	while ($recording = mysqli_fetch_assoc($result))
	{
		$recordings[] = $recording;
	}
	mysqli_close($mysql);

	return $recordings;
}

function get_labelled_size($size)
{
	$sizes = array('GB' => 30, 'MB' => 20, 'KB' => 10);

	foreach ($sizes as $label => $power)
	{
		$power = pow(2, $power);

		if ($size > $power)
		{
			return sprintf('%0.2f'.$label, $size / $power);
		}
	}

	return sprintf('%0.2fB', $size);
}

?>
<!DOCTYPE HTML PUBLIC '-//W3C//DTD HTML 4.01//EN' 'http://www.w3.org/TR/html4/strict.dtd'>
<html>
<head>
<link rel='icon' href='/skins/default/img/favicon.ico' type='image/x-icon'>
<link rel='shortcut icon' href='/skins/default/img/favicon.ico' type='image/x-icon'>
<title>MythTV Recordings</title>
<style type='text/css'>
td a
{
	color: black;
	text-decoration: none;
}

th a
{
	color: white;
	text-decoration: none;
}

h1 a
{
	color: black;
	text-decoration: none;
}

body
{
	font-family: sans-serif;
	font-size: 10pt;
}

table
{
	border-collapse: collapse;
	width: 100%;
}

th
{
	background-color: black;
	border: 1px solid black;
	color: white;
	padding: 2px 2px 2px 2px;
	text-align: center;
}

td
{
	border: 1px solid black;
	padding: 2px 2px 2px 2px;
	white-space: nowrap;
}

.c_td
{
	text-align: center;
}

.l_td
{
	text-align: left;
}

.r_td
{
	text-align: right;
}
</style>
</head>
<body>
<h1><a href='<?php echo $_SERVER['SCRIPT_NAME']; ?>'>MythTV Recordings</a></h1>
<table>
<?php

$f = disk_free_space('/srv/mythtv/storage/default') + disk_free_space('/srv/nas.grufty.co.uk/mythtv/default');
$t = disk_total_space('/srv/mythtv/storage/default') + disk_total_space('/srv/nas.grufty.co.uk/mythtv/default');
$a = get_bit_rate_average();
$m = get_bit_rate_maximum();
$s = get_seconds_total(); // Not the same as $t - $f due to OS and other things

echo '<tr><td colspan=\''.COLSPAN.'\' style=\'border: 0px;\'><h2>Disk Space</h2></td></tr>'.PHP_EOL;
echo_disk_row('Free', $f, $t, COLSPAN, get_bit_rate_text($f, $a, 'available', 'average'));
echo_disk_row('Used', $t - $f, $t, COLSPAN, get_bit_rate_text($f, $m, 'available', 'maximum'));
echo_disk_row('Total', $t, $t, COLSPAN, get_bit_rate_text($s * ($a / 8), $a, 'recorded', 'average'));

unset($f, $t, $a, $m, $s);

$order_bys = array(
	'Callsign'	=> 'callsign,title,subtitle',
	'Title'		=> 'title,subtitle,filesize',
	'Subtitle'	=> 'subtitle,title,filesize',
	'Link'		=> 'inetref,filesize,title',
	'Basename'	=> 'basename,title,subtitle',
	'Size'		=> 'filesize,title,subtitle',
	'Start'		=> 'progstart_system,filesize,title',
	'End'		=> 'progend_system,filesize,title',
	'Length'	=> 'proglength,filesize,title',
	'Bitrate'	=> 'bitrate,filesize,title',
);

$order_directions = array(
	'ASC',
	'DESC');

if (!isset($_REQUEST['order_by']) || !in_array($_REQUEST['order_by'], $order_bys))
{
	$_REQUEST['order_by'] = 'filesize,title,subtitle';
}

if (!isset($_REQUEST['order_direction']) || !in_array($_REQUEST['order_direction'], $order_directions))
{
	$_REQUEST['order_direction'] = 'DESC';
}

$recordings = get_recordings($_REQUEST['order_by'], $_REQUEST['order_direction']);

$subtitles = array();
foreach ($recordings as $recording)
{
	if (strlen($recording['subtitle']))
	{
		$subtitles[$recording['recgroup']] = true;
	}
}

$group = null;
foreach ($recordings as $recording)
{
	if ($group == null || $group != $recording['recgroup'])
	{
		$group = $recording['recgroup'];

		echo '<tr><td colspan=\''.COLSPAN.'\' style=\'border: 0px;\'>&nbsp;</td></tr>'.PHP_EOL;

		echo '<tr><td colspan=\''.COLSPAN.'\' style=\'border: 0px;\'><h2>'.htmlentities($group, ENT_QUOTES).' Group</h2></td></tr>'.PHP_EOL;

		echo '<tr>'.PHP_EOL;
		foreach ($order_bys as $name => $order_by)
		{
			echo '<th><a href=\'?order_by='.urlencode($order_by).'&amp;order_direction=';

			if ($_REQUEST['order_by'] == $order_by)
			{
				if ($_REQUEST['order_direction'] == 'ASC')
				{
					echo urlencode('DESC');
				}
				else
				{
					echo urlencode('ASC');
				}
			}
			else
			{
				switch ($name)
				{
				case 'Callsign':
				case 'Title':
				case 'Subtitle':
				case 'Basename':
					echo urlencode('ASC');
					break;

				case 'Size':
				case 'Start':
				case 'End':
				case 'Length':
				case 'Bitrate':
				case 'Link':
					echo urlencode('DESC');
					break;
				}
			}

			echo '\' style=\'display: inline-block; height: 100%; width: 100%;\'>'.htmlentities($name, ENT_QUOTES).'</a></th>'.PHP_EOL;
		}
		echo '</tr>'.PHP_EOL;
	}

	if ($group == $recording['recgroup'])
	{
		echo '<tr>'.PHP_EOL;
		echo '<td class=\'l_td\'>'.htmlentities($recording['callsign'], ENT_QUOTES).'</td>'.PHP_EOL;
		echo '<td class=\'l_td\'>'.htmlentities($recording['title'], ENT_QUOTES).'</td>'.PHP_EOL;

		echo '<td class=\'l_td\'>'.PHP_EOL;

		if (isset($subtitles[$group]) && $subtitles[$group] == true)
		{
			echo htmlentities($recording['subtitle'], ENT_QUOTES);
		}
		else
		{
			echo '&nbsp;';
		}

		echo '</td>'.PHP_EOL;

		echo '<td class=\'c_td\'>'.PHP_EOL;

		if (isset($recording['inetref']) && $recording['inetref'] != '')
		{
			if (preg_match('/^tmdb3\.py_[0-9]+$/', $recording['inetref']) == 1)
			{
				echo '<a href=\'https://www.themoviedb.org/movie/'.urlencode(preg_replace('/^tmdb3\.py_/', '', $recording['inetref'])).'\' target=\'_blank\'>Link</a>'.PHP_EOL;
			}
			else if (preg_match('/^ttvdb\.py_[0-9]+$/', $recording['inetref']) == 1)
			{
				echo '<a href=\'https://www.themoviedb.org/movie/'.urlencode(preg_replace('/^ttvdb\.py_/', '', $recording['inetref'])).'\' target=\'_blank\'>Link</a>'.PHP_EOL;
			}
			else
			{
				echo $recording['inetref'];
			}
		}
		else
		{
			echo '&nbsp;';
		}

		echo '</td>'.PHP_EOL;

		echo '<td class=\'r_td\'>'.htmlentities($recording['basename'], ENT_QUOTES).'</td>'.PHP_EOL;
		echo '<td class=\'r_td\'>'.htmlentities(get_labelled_size($recording['filesize']), ENT_QUOTES).'</td>'.PHP_EOL;
		echo '<td class=\'r_td\'>'.htmlentities(get_date_time($recording['progstart_system']), ENT_QUOTES).'</td>'.PHP_EOL;
		echo '<td class=\'r_td\'>'.htmlentities(get_date_time($recording['progend_system']), ENT_QUOTES).'</td>'.PHP_EOL;
		echo '<td class=\'r_td\'>'.htmlentities(get_length($recording['proglength']), ENT_QUOTES).'</td>'.PHP_EOL;
		echo '<td class=\'r_td\'>'.htmlentities(sprintf('%d', $recording['bitrate'] / 1024.0), ENT_QUOTES).'</td>'.PHP_EOL;
		echo '</tr>'.PHP_EOL;
	}
}
?>
</table>
<h2>Title Grouping</h2>
<table>
<tr>
	<th>Title</th>
	<th>Group</th>
	<th>Size</th>
</tr>
<?php
$recordings = get_recordings_grouped();

foreach ($recordings as $recording)
{
	echo '<tr>'.PHP_EOL;
	echo '<td>'.htmlentities($recording['title'], ENT_QUOTES);
	if ($recording['count'] > 1)
	{
		echo ' ('.htmlentities($recording['count'], ENT_QUOTES).')';
	}
	echo '</td>'.PHP_EOL;
	echo '<td>'.htmlentities($recording['recgroup'], ENT_QUOTES).'</td>'.PHP_EOL;
	echo '<td>'.htmlentities(get_labelled_size($recording['filesize']), ENT_QUOTES).'</td>'.PHP_EOL;
	echo '</tr>'.PHP_EOL;
}

?>
</table>
</body>
</html>
