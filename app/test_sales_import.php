<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sales Import Test</title>
</head>

<body>

<h2>Sales Import Test</h2>

<form
    action="dashboard/data_management/ajax/import_sales.php"
    method="POST"
    enctype="multipart/form-data"
>

    <input
        type="file"
        name="sales_file"
        accept=".csv,.xlsx"
        required
    >

    <button type="submit">
        Import Sales
    </button>

</form>

</body>
</html>