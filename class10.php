<?php
// class10.php - Class 10 Routine Page
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Class 10 Routine | Coaching Center</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body{
            margin:0;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(to right, #0F172A, #1E293B);
            color: #fff;
        }

        .container{
            width: 90%;
            margin: auto;
            padding: 40px 0;
        }

        h1{
            text-align: center;
            margin-bottom: 10px;
        }

        .subtitle{
            text-align: center;
            margin-bottom: 40px;
            color: #38BDF8;
        }

        .card{
            background: #1E293B;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
            transition: 0.3s;
        }

        .card:hover{
            transform: translateY(-5px);
        }

        table{
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table, th, td{
            border: 1px solid #334155;
        }

        th, td{
            padding: 12px;
            text-align: center;
        }

        th{
            background-color: #06B6D4;
            color: #000;
        }

        .time{
            font-weight: bold;
            color: #FACC15;
        }

        .group-badge{
            display: inline-block;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: bold;
            margin: 10px 5px 20px 5px;
        }

        .science{
            background: #2563eb;
            color: white;
        }

        .humanities{
            background: #9333ea;
            color: white;
        }

        .commerce{
            background: #16a34a;
            color: white;
        }

        .back-btn{
            display: inline-block;
            margin-top: 30px;
            padding: 12px 25px;
            background: #06B6D4;
            color: #000;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: 0.3s;
        }

        .back-btn:hover{
            background: #38BDF8;
            transform: translateY(-2px);
        }

        footer{
            text-align: center;
            padding: 15px;
            background: #0F172A;
            margin-top: 40px;
            font-size: 14px;
        }

    </style>
</head>
<body>

<div class="container">

    <h1>📚 Class 10 (All Groups)</h1>
    <p class="subtitle">Science | Humanities | Commerce</p>

    <div style="text-align: center; margin-bottom: 30px;">
        <span class="group-badge science">Science</span>
        <span class="group-badge humanities">Humanities</span>
        <span class="group-badge commerce">Commerce</span>
    </div>

   
 

    <!-- Science Group Extra Subjects -->
    <div class="card">
        <h2>🔬 Science Group (Additional Subjects)</h2>
        <table>
            <tr>
                <th>Day</th>
                <th>Subject</th>
                <th>Time</th>
            </tr>
            <tr>
                <td>Sunday, Tuesday</td>
                <td>Physics</td>
                <td>4:30 PM – 5:30 PM</td>
            </tr>
            <tr>
                <td>Monday, Wednesday</td>
                <td>Chemistry</td>
                <td>4:30 PM – 5:30 PM</td>
            </tr>
            <tr>
                <td>Thursday, Saturday</td>
                <td>Biology</td>
                <td>4:30 PM – 5:30 PM</td>
            </tr>
            <tr>
                <td>Sunday, Tuesday</td>
                <td>Higher Mathematics</td>
                <td>5:45 PM – 6:45 PM</td>
            </tr>
        </table>
    </div>

    <!-- Humanities Group Extra Subjects -->
    <div class="card">
        <h2>🌍 Humanities Group (Additional Subjects)</h2>
        <table>
            <tr>
                <th>Day</th>
                <th>Subject</th>
                <th>Time</th>
            </tr>
            <tr>
                <td>Saturday, Tuesday</td>
                <td>History</td>
                <td>4:30 PM – 5:30 PM</td>
            </tr>
            <tr>
                <td>Sunday, Wednesday</td>
                <td>Geography</td>
                <td>4:30 PM – 5:30 PM</td>
            </tr>
            <tr>
                <td>Monday, Thursday</td>
                <td>Civics</td>
                <td>4:30 PM – 5:30 PM</td>
            </tr>
            <tr>
                <td>Saturday, Tuesday</td>
                <td>Economics</td>
                <td>5:45 PM – 6:45 PM</td>
            </tr>
            <tr>
                <td>Sunday, Wednesday</td>
                <td>Social Work</td>
                <td>5:45 PM – 6:45 PM</td>
            </tr>
            <tr>
                <td>Monday, Thursday</td>
                <td>Islamic History</td>
                <td>5:45 PM – 6:45 PM</td>
            </tr>
        </table>
    </div>

    <!-- Commerce Group Extra Subjects -->
    <div class="card">
        <h2>💼 Commerce Group (Additional Subjects)</h2>
        <table>
            <tr>
                <th>Day</th>
                <th>Subject</th>
                <th>Time</th>
            </tr>
            <tr>
                <td>Saturday, Tuesday</td>
                <td>Accounting</td>
                <td>4:30 PM – 5:30 PM</td>
            </tr>
            <tr>
                <td>Sunday, Wednesday</td>
                <td>Business Studies</td>
                <td>4:30 PM – 5:30 PM</td>
            </tr>
            <tr>
                <td>Monday, Thursday</td>
                <td>Finance & Banking</td>
                <td>4:30 PM – 5:30 PM</td>
            </tr>
            <tr>
                <td>Saturday, Tuesday</td>
                <td>Economics</td>
                <td>5:45 PM – 6:45 PM</td>
            </tr>
            <tr>
                <td>Sunday, Wednesday</td>
                <td>Business Mathematics</td>
                <td>5:45 PM – 6:45 PM</td>
            </tr>
            <tr>
                <td>Monday, Thursday</td>
                <td>Entrepreneurship</td>
                <td>5:45 PM – 6:45 PM</td>
            </tr>
        </table>
    </div>

    <div style="text-align: center;">
        <a href="index.php" class="back-btn">⬅ Back to Home</a>
    </div>

</div>

<footer>
    © <?php echo date("Y"); ?> Coaching Center Management System | All Rights Reserved
</footer>

</body>
</html>