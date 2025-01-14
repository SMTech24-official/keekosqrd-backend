<!DOCTYPE html>
<html>
<head>
    <title>Contact Form Submission</title>
</head>
<body>
    <h1>New Contact Form Submission</h1>
    <p><strong>Name:</strong> {{ $data->name }}</p>
    <p><strong>Email:</strong> {{ $data->email }}</p>
    <p><strong>Phone:</strong> {{ $data->phone }}</p>
    <p><strong>Company:</strong> {{ $data->company }}</p>
    <p><strong>Query:</strong> {{ $data->query }}</p>
</body>
</html>
