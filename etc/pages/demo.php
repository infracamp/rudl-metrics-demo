<?php
namespace Page;

use Phore\Html\Fhtml\FHtml;

/* @var $database \InfluxDB\Database */


$nodeData = $database->query("SELECT DISTINCT(host) as host FROM node_stat GROUP BY cluster, host")->getPoints();


$nodes = [];



function parseDate(string $in) : \DateTime {
    if (preg_match("/^([0-9\-]+)T([0-9\:]+)/", $in, $matches)) {
        return new \DateTime($matches[1] . "T" . $matches[2], new \DateTimeZone("GMT"));
    }
    throw new \InvalidArgumentException("Cannot parse time $in");
}


function limit($value, $warn, $err, $opt="") : FHtml {
    $style = "color: green";
    if ($value > $warn)
        $style = "color: gold";
    if ($value > $err)
        $style = "color: red";

    return \fhtml(["span @style=:style" => $value . $opt], ["style"=>$style]);

}


$lastClusterName = "";
foreach ($nodeData as $cur) {

    $hostName = $cur["host"];
    $nodeInfo = [
        $cur["cluster"] != $lastClusterName ? $cur["cluster"] : "",
        $hostName,
    ];
    $lastClusterName = $cur["cluster"];

    $hData = $database->query("SELECT * FROM node_stat WHERE host='$hostName' ORDER BY time DESC LIMIT 1")->getPoints()[0];


    $date = parseDate($hData["time"]);

    $nodeInfo[] = limit(time() - $date->getTimestamp(), 6, 60, "sec");
    $nodeInfo[] = limit($hData["loadavg"], 2, 8);
    $nodeInfo[] = limit($hData["loadavg_5m"], 2, 8);
    $nodeInfo[] = limit($hData["loadavg_15m"], 2, 8);
    $nodeInfo[] = limit($hData["fs_use_prct"], 70, 90, "%");
    $nodeInfo[] = limit($hData["fs_iuse_prct"], 70, 90, "%");

    $nodeInfo[] = (int)($hData["mem_avail_kb"] / 1024 / 1024) . "GB";
    $nodeInfo[] = (int)($hData["fs_avail_kb"] / 1024 / 1024) . "GB";

    $nodes[] = $nodeInfo;

}

echo "<link rel=\"stylesheet\" href=\"//cdn.fuman.de/bootstrapcdn/bootstrap/3.3.4/css/bootstrap.min.css\">";


echo pt("table-striped table-hover")->basic_table(
    ["Cluster", "Node", "Last seen", "loadavg", "loadavg5m", "loadavg15m", "fs", "inode", "mem_free", "hdd_free"],
    $nodes
);
