<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sales Import Preview Test</title>
</head>

<body>

<h2>Sales Import Preview Test</h2>

<form
    action="dashboard/data_management/ajax/preview_sales.php"
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
        Test Sales Preview
    </button>

</form>

</body>
</html>