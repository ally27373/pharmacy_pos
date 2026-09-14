<?php

$period = $_GET['period'] ?? '7days';

?>

<div class="dashboard-header">

    <div>

        <h2 class="dashboard-title">

            Analytics Dashboard

        </h2>

        <p class="dashboard-subtitle">

            Welcome back,
            <strong><?= htmlspecialchars($_SESSION['username']); ?></strong>

        </p>

    </div>

    <div class="dashboard-actions">

        <select
    class="dashboard-filter"
    id="dashboardFilter"
    onchange="changePeriod(this.value)">

    <option value="today" <?= $period=='today'?'selected':''; ?>>
        Today
    </option>

    <option value="7days" <?= $period=='7days'?'selected':''; ?>>
        Last 7 Days
    </option>

    <option value="30days" <?= $period=='30days'?'selected':''; ?>>
        Last 30 Days
    </option>

    <option value="3months" <?= $period=='3months'?'selected':''; ?>>
        Last 3 Months
    </option>

    <option value="1year" <?= $period=='1year'?'selected':''; ?>>
        This Year
    </option>

    <option value="all" <?= $period=='all'?'selected':''; ?>>
        All Time
    </option>

</select>

    </div>

</div>

<script>

function changePeriod(period)
{
    const url = new URL(window.location.href);

    url.searchParams.set("period", period);

    window.location.href = url.toString();
}

</script>

