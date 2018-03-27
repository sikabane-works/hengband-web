<?php
//ini_set('display_errors', 'On');

ini_set('log_errors', 'On');
ini_set('error_log', 'errors/'.pathinfo(__FILE__, PATHINFO_FILENAME).'.log');

require_once "db_common.inc";
require_once "dump_file.inc";

ini_set('zlib.output_compression', 'On');


/**
 * ページ情報を計算する
 *
 * @param integer $total_data_count 全データ件数
 * @param integer $start_num GETパラメータで渡された開始データ番号
 * @param integer $data_count_per_page 1ページあたりのデータ件数
 *
 * @return array 計算したページ情報を保持する連想配列
 */
function calc_page_info($total_data_count, $start_num, $data_count_per_page)
{
    $current_page = intval($start_num / $data_count_per_page);
    $last_page = intval(($total_data_count - 1) / $data_count_per_page);
    $navi_page_range_start = ($current_page - 5) > 0 ? $current_page - 5: 0;
    $navi_page_range_count = max(0, min(9, $last_page - $navi_page_range_start));
    $navi_page_list = range($navi_page_range_start, $navi_page_range_start + $navi_page_range_count);

    $pageinfo['current'] = $current_page;
    $pageinfo['last'] = $last_page;
    $pageinfo['navi_list'] = $navi_page_list;

    $pageinfo['total_data_count'] = $total_data_count;
    $pageinfo['data_count_per_page'] = $data_count_per_page;

    return $pageinfo;
}


/**
 * ページナビゲーションテーブルを表示する
 *
 * @param array $pageinfo calc_page_info()関数で取得したページ情報を保持する連想配列
 */
function print_navi_page_table($pageinfo)
{
    if (count($pageinfo['navi_list']) <= 1) return;

    $href_base = filter_input(INPUT_SERVER, 'SCRIPT_NAME')."?"
               .preg_replace('/(&?start=\w+)/', '', filter_input(INPUT_SERVER, 'QUERY_STRING'));
    if (strpos($href_base, "?") === FALSE) {
        $href_base .= "?";
    }

    echo "<table align='center'>\n"
        ."<tr>";

    if ($pageinfo['current'] > 0) {
        $href = $href_base . "&start=". ($pageinfo['current'] - 1) * $pageinfo['data_count_per_page'];
        echo "<td><a href={$href}>&lt; 前へ</a></td>";
    }

    foreach ($pageinfo['navi_list'] as $page) {
        $page_num = $page + 1;
        $href = $href_base . "&start=". $page * $pageinfo['data_count_per_page'];
        if ($page === $pageinfo['current']) {
            echo "<td>$page_num</td>";
        } else {
            echo "<td><a href={$href}>$page_num</a></td>";
        }
    }

    if ($pageinfo['current'] < $pageinfo['last']) {
        $href = $href_base . "&start=". ($pageinfo['current'] + 1) * $pageinfo['data_count_per_page'];
        echo "<td><a href={$href}>次へ &gt;</a></td>";
    }

    echo "</tr>\n"
        ."</table>\n";
}


/**
 * スコアランキングテーブルを表示する
 *
 * @param array $scores スコア
 * @param integer $rank_start 順位の開始番号(0オリジン)
 */
function print_score_table($scores, $rank_start)
{
    echo <<<EOM
<table align='center' border=1>
<tr>
<th>順位</th>
<th>スコア</th>
<th>日付</th>
<th>名前</th>
<th>種族</th>
<th>職業</th>
<th>性別</th>
<th>死因</th>
</tr>

EOM;

    foreach($scores as $idx => $score) {
        $rank = $rank_start + $idx + 1;
        $date = substr($score['date'], 0, 10); // 日時から日付部分を取り出す
        $sex_str = $score['sex'] ? "男" : "女";
        $depth = !$score['winner'] ? $score['depth']."階, " : "";
        $realms = isset($score['realms_name']) ? "(".$score['realms_name'].")" : "";
        $dumpfile = new DumpFile($score['score_id']);

        echo "<tr>\n";
        if ($dumpfile->exists('dumps', 'txt')) {
            $name = "<a href=\"show_dump.php?score_id={$score['score_id']}\">{$score['personality_name']}{$score['name']}</a>\n";
        } else {
            $name = "{$score['personality_name']}{$score['name']}";
        }
        echo <<<EOM
<td>$rank</td>
<td align="right">{$score['score']}</td>
<td><nobr>$date</nobr></td>
<td>$name</td>
<td>{$score['race_name']}</td>
<td>{$score['class_name']}$realms</td>
<td>$sex_str</td>

EOM;
        if ($dumpfile->exists('screens', 'html')) {
            echo "<td><a href=\"show_screen.php?score_id={$score['score_id']}\">{$score['death_reason']}</a>";
        } else {
            echo "<td>{$score['death_reason']}";
        }
        echo "<br>({$depth}{$score['version']})</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

$db = new ScoreDB();

$start_num = filter_input(INPUT_GET, 'start', FILTER_VALIDATE_INT) ?: 0;
$search_result = $db->search_score($start_num, 50);

$pageinfo = calc_page_info($search_result['total_data_count'], $start_num, 50);
?>

<!DOCTYPE html>

<html lang="jp">
        <head>
                <meta charset="utf-8"/>
                <link rev=made href="mailto:hengband-dev@lists.sourceforge.jp">
                <link rel="stylesheet" type="text/css" href="/hengband.css">
                <link rel="alternate" title="変愚蛮怒 新着スコア" href="feed/newcome-atom.xml" type="application/atom+xml" />
                <title>変愚蛮怒 公式WEB スコアランキング</title>
        </head>

        <body>

                <header>

                        <section id="title">
                                <img class="tama1" src="/image/tama.gif" alt="tama">
                                <img class="tama2" src="/image/tama.gif" alt="tama">
                                <img class="tama3" src="/image/tama.gif" alt="tama">
                                <img class="tama4" src="/image/tama.gif" alt="tama">
                                <img id="hengTitle" src="/image/hengband_title.png" alt="変愚蛮怒 Hengband">
                                <img class="tama4" src="/image/tama.gif" alt="tama">
                                <img class="tama3" src="/image/tama.gif" alt="tama">
                                <img class="tama2" src="/image/tama.gif" alt="tama">
                                <img class="tama1" src="/image/tama.gif" alt="tama">
                        </section>

                        <section id="mainMenu">
                                <a href="/index.html">トップ</a>
                                <a href="/download.html">ダウンロード</a>
                                <a href="/score.html">スコア</a>
                                <a href="/lists.html">コミュニティ</a>
                                <a href="/history.html">バージョン履歴</a>
                                <a href="/link.html">関連リンク</a>
                                <a href="/jlicense.html">著作権表記</a>
                                <span>English (Coming Soon)</span>
                        </section>

                </header>
 
                <div id="main">
<!--main contents-->
<h2>変愚蛮怒 歴代スコア (<?php echo $db->get_sort_mode_name(); ?>)</h2>
<!--3日以内のスコアは<font color=red>赤</font>、10日以内のスコアは<font color=blue>青</font>で表示されます。<br>-->
<!--10日以内のスコアは<strong>強調表示</strong>されます。-->
<div>
<div align="right">
<a href ="feed/newcome-atom.xml"><img src="feed/feed-icon-14x14.png">新着スコア購読</a>
<!--<small>…スコア受信時に自動生成します。URLをRSSリーダー等に登録すると新着スコアをチェックできます。</small>-->
<br>
<small>
<?php
echo sprintf("件数 %d 件 (%.2f 秒)", $search_result['total_data_count'], $search_result['elapsed_time']);
?>
</small>
</div>
<?php
print_navi_page_table($pageinfo);
print_score_table($search_result['scores'], $pageinfo['current'] * $pageinfo['data_count_per_page']);
print_navi_page_table($pageinfo);
?>
</div>
                </div>

                <footer>

                        <section>
                                各ページへのリンクは御自由にどうぞ。/ Link Free.<br>
                                2018 Hengband Dev Team. <a href="mailto:hengband-dev@lists.sourceforge.jp">hengband-dev@lists.sourceforge.jp</a><br>
                        </section>

                        <section>
                                Powered by <a href="https://ja.osdn.net/" class="footer_banner">
                                <img src="https://ja.osdn.net/sflogo.php?group_id=541" border="0" alt="OSDN.jp">
                                </a>
                        </section>

                </footer>

        </body>

</html>