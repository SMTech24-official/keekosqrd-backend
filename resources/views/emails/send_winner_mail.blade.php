<!DOCTYPE html>
<html>
<head>
    <title>Congratulations!</title>
</head>
<body>
    <h1>Congratulations, {{ $winnerName }}!</h1>
    <p>You are the winner of this month's sneaker voting contest!</p>
    <p>Here are the details of your sneaker:</p>
    <ul>
        <li><strong>Model:</strong> {{ $sneakerDetails['model'] }}</li>
        <li><strong>Size:</strong> {{ $sneakerDetails['size'] }}</li>
    </ul>
    <p>We will contact you shortly for shipping details.</p>
    <p>Thank you for participating!</p>
</body>
</html>
