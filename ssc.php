<?php
// ssc.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SSC Batch Curriculum | Coaching Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body{
            margin:0;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(to right, #0F172A, #1E293B);
            color:#fff;
        }

        .container{
            width:90%;
            margin:auto;
            padding:40px 0;
        }

        h1{
            text-align:center;
            margin-bottom:10px;
        }

        .subtitle{
            text-align:center;
            color:#38BDF8;
            margin-bottom:40px;
        }

        .card{
            background:#1E293B;
            padding:25px;
            border-radius:12px;
            margin-bottom:30px;
            box-shadow:0 8px 20px rgba(0,0,0,0.4);
            transition:0.3s;
        }

        .card:hover{
            transform:translateY(-5px);
        }

        table{
            width:100%;
            border-collapse:collapse;
            margin-top:15px;
        }

        table, th, td{
            border:1px solid #334155;
        }

        th, td{
            padding:12px;
            text-align:center;
        }

        th{
            background:#06B6D4;
            color:#000;
        }

        .back-btn{
            display:inline-block;
            margin-top:30px;
            padding:10px 20px;
            background:#06B6D4;
            color:#000;
            text-decoration:none;
            border-radius:8px;
            font-weight:bold;
        }

        .back-btn:hover{
            background:#38BDF8;
        }

        footer{
            text-align:center;
            padding:15px;
            background:#0F172A;
            margin-top:40px;
            font-size:14px;
        }
    </style>
</head>
<body>

<div class="container">

    <h1>SSC Batch Curriculum</h1>
    <p class="subtitle">Science | Commerce | Humanities</p>

    <!-- SCIENCE GROUP -->
    <div class="card">
        <h2>🔬 Science Group Routine</h2>
        <table>
            <tr>
                <th>Day</th>
                <th>Subject</th>
                <th>Time</th>
            </tr>
            <tr>
                <td>Saturday</td>
                <td>Physics + Chemistry</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Sunday</td>
                <td>Biology</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Monday</td>
                <td>Higher Mathematics</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Tuesday</td>
                <td>General Mathematics</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Wednesday</td>
                <td>Bangla (1st & 2nd Paper)</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Thursday</td>
                <td>English (1st & 2nd Paper)</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
        </table>
    </div>

    <!-- COMMERCE GROUP -->
    <div class="card">
        <h2>💼 Commerce Group Routine</h2>
        <table>
            <tr>
                <th>Day</th>
                <th>Subject</th>
                <th>Time</th>
            </tr>
            <tr>
                <td>Saturday</td>
                <td>Accounting</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Sunday</td>
                <td>Finance & Banking</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Monday</td>
                <td>Business Entrepreneurship</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Tuesday</td>
                <td>General Mathematics</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Wednesday</td>
                <td>Bangla (1st & 2nd Paper)</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Thursday</td>
                <td>English (1st & 2nd Paper)</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
        </table>
    </div>

    <!-- HUMANITIES GROUP -->
    <div class="card">
        <h2>🌍 Humanities Group Routine</h2>
        <table>
            <tr>
                <th>Day</th>
                <th>Subject</th>
                <th>Time</th>
            </tr>
            <tr>
                <td>Saturday</td>
                <td>History</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Sunday</td>
                <td>Geography</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Monday</td>
                <td>Civics + Economics</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Tuesday</td>
                <td>General Mathematics</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Wednesday</td>
                <td>Bangla (1st & 2nd Paper)</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
            <tr>
                <td>Thursday</td>
                <td>English (1st & 2nd Paper)</td>
                <td>2:20 PM – 4:20 PM</td>
            </tr>
        </table>
    </div>

    <a href="index.php" class="back-btn">⬅ Back to Home</a>

</div>

<footer>
    © <?php echo date("Y"); ?> Coaching Center Management System | SSC Batch
</footer>

</body>
</html>