<?php
/**
 * ==========================================================
 * Dashboard Metrics
 * Displays the four KPI cards
 * ==========================================================
 */

/** @var array $dashboardData */
?>

<?php

$labels = [];
$data = [];

foreach ($dashboardData['revenue_by_category'] as $row){

    $labels[] = (string) $row['category_name'];

    $data[] = (float) $row['revenue'];

}

/*
 * The payload is emitted inside a <script type="application/json"> island
 * instead of being inlined into the chart script.
 *
 * Rationale: json_encode() returns false on malformed UTF-8, and a raw
 * inline interpolation would then emit *nothing*, producing broken JS
 * ("missing ) after argument list") that silently kills the whole block
 * and leaves an empty canvas. A JSON island is never parsed as JS by the
 * browser, so no data value can ever break the chart script. JSON_HEX_TAG
 * additionally neutralises a literal "</script>" inside a product name.
 */
$revenueLabelsJson = json_encode($labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
$revenueDataJson = json_encode($data);

if (!is_string($revenueLabelsJson) || $revenueLabelsJson === '') {
    $revenueLabelsJson = '[]';
}

if (!is_string($revenueDataJson) || $revenueDataJson === '') {
    $revenueDataJson = '[]';
}

?>

<div class="dashboard-panel">

    <h4>Revenue by Category</h4>

<?php if(array_sum($data)==0): ?>

<div class="empty-chart">

    <i class="bi bi-bar-chart-fill"></i>

    <h5>No Revenue Yet</h5>

    <p>

        Revenue analytics will appear after
        completed sales transactions.

    </p>

</div>

<?php else: ?>

    <div class="chart-container revenue-chart-container">

        <canvas id="revenueChart"></canvas>

    </div>



<script type="application/json" id="revenue-chart-labels"><?= $revenueLabelsJson ?></script>
<script type="application/json" id="revenue-chart-data"><?= $revenueDataJson ?></script>

<script>

(function () {

var payloadEl = document.getElementById("revenue-chart-labels");
var dataEl = document.getElementById("revenue-chart-data");
var canvas = document.getElementById("revenueChart");

if (!payloadEl || !dataEl || !canvas || typeof Chart === "undefined") {
    return;
}

var labels = [];
var values = [];

try {
    labels = JSON.parse(payloadEl.textContent) || [];
    values = JSON.parse(dataEl.textContent) || [];
} catch (parseError) {
    console.error("Revenue chart payload could not be parsed:", parseError);
    return;
}

if (!labels.length) {
    canvas.style.display = "none";
    var note = document.createElement("div");
    note.className = "batch-empty";
    note.textContent = "Revenue data is unavailable for this period.";
    canvas.parentNode.appendChild(note);
    return;
}

new Chart(canvas,{

type:"bar",

indexAxis:"y",

data:{

labels: labels,

datasets:[{

data: values,

backgroundColor:[

"#118d6d","#e63946","#fb8500","#ffb703","#07b5cf",

"#9b59b6","#adb5bd","#457b9d","#e63946"

],

borderWidth:0,

borderRadius:4,

barThickness:20

}]

},

options:{

responsive:true,

maintainAspectRatio:false,

plugins:{

legend:{

display:false

},

tooltip:{

backgroundColor:"#1a2230",

padding:10,

cornerRadius:6,

font:{size:12}

}

}

}

});

})();

</script>

<?php endif; ?>

</div>

