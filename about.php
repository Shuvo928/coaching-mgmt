<?php
// about.php - About Us Page
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About Us | Coaching Pro</title>
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
            font-size: 42px;
        }

        .subtitle{
            text-align: center;
            margin-bottom: 50px;
            color: #38BDF8;
            font-size: 18px;
        }

        .card{
            background: #1E293B;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
            transition: 0.3s;
        }

        .card:hover{
            transform: translateY(-5px);
        }

        .mission-vision{
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .mission, .vision{
            background: #1E293B;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }

        .mission h2, .vision h2{
            color: #38BDF8;
            border-bottom: 2px solid #06B6D4;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .stats{
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 40px 0;
        }

        .stat-box{
            background: #1E293B;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }

        .stat-number{
            font-size: 36px;
            font-weight: bold;
            color: #06B6D4;
            margin-bottom: 10px;
        }

        .stat-label{
            color: #94A3B8;
        }

        .team-grid{
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-top: 30px;
        }

        .team-member{
            background: #1E293B;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }

        .team-member img{
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin-bottom: 15px;
            border: 3px solid #06B6D4;
        }

        .member-name{
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .member-role{
            color: #38BDF8;
            margin-bottom: 10px;
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
            padding: 20px;
            background: #0F172A;
            margin-top: 40px;
            font-size: 14px;
        }

        @media (max-width: 768px){
            .mission-vision{
                grid-template-columns: 1fr;
            }
            .stats{
                grid-template-columns: repeat(2, 1fr);
            }
            .team-grid{
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <h1>📖 About Coaching Pro</h1>
    <p class="subtitle">Empowering Students Since 2015 • Dhaka, Bangladesh</p>

    <!-- Mission & Vision -->
    <div class="mission-vision">
        <div class="mission">
            <h2>🎯 Our Mission</h2>
            <p style="line-height: 1.8; color: #CBD5E1;">To provide quality education and comprehensive academic support to students of Class 9, Class 10, and SSC candidates. We strive to create a nurturing learning environment that fosters academic excellence, critical thinking, and character development.</p>
        </div>
        <div class="vision">
            <h2>🔭 Our Vision</h2>
            <p style="line-height: 1.8; color: #CBD5E1;">To become Bangladesh's most trusted coaching center network, recognized for academic excellence, innovative teaching methods, and holistic student development. We aim to shape future leaders through quality education.</p>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats">
        <div class="stat-box">
            <div class="stat-number">10+</div>
            <div class="stat-label">Years of Excellence</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">5000+</div>
            <div class="stat-label">Students Trained</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">50+</div>
            <div class="stat-label">Expert Teachers</div>
        </div>
        <div class="stat-box">
            <div class="stat-number">3</div>
            <div class="stat-label">Branches in Dhaka</div>
        </div>
    </div>

    <!-- Why Choose Us -->
    <div class="card">
        <h2 style="color: #38BDF8; margin-bottom: 20px;">✨ Why Choose Coaching Pro?</h2>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;">
            <div style="background: #0F172A; padding: 20px; border-radius: 8px;">
                <h3 style="color: #06B6D4;">📚 Structured Curriculum</h3>
                <p style="color: #94A3B8;">Board-aligned syllabus with systematic progression</p>
            </div>
            <div style="background: #0F172A; padding: 20px; border-radius: 8px;">
                <h3 style="color: #06B6D4;">👨‍🏫 Expert Teachers</h3>
                <p style="color: #94A3B8;">Highly qualified and experienced faculty members</p>
            </div>
            <div style="background: #0F172A; padding: 20px; border-radius: 8px;">
                <h3 style="color: #06B6D4;">📊 Progress Tracking</h3>
                <p style="color: #94A3B8;">Regular assessments and performance analytics</p>
            </div>
            <div style="background: #0F172A; padding: 20px; border-radius: 8px;">
                <h3 style="color: #06B6D4;">🏆 Success Record</h3>
                <p style="color: #94A3B8;">95% students achieve A+ in board exams</p>
            </div>
        </div>
    </div>

    <!-- Our Branches -->
    <div class="card">
        <h2 style="color: #38BDF8; margin-bottom: 20px;">📍 Our Branches</h2>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div style="background: #0F172A; padding: 20px; border-radius: 8px;">
                <h3 style="color: #06B6D4;">Dhanmondi</h3>
                <p style="color: #94A3B8;">Road 27, House 45<br>Dhanmondi, Dhaka 1205</p>
                <p style="color: #38BDF8;">📞 01712-345678</p>
            </div>
            <div style="background: #0F172A; padding: 20px; border-radius: 8px;">
                <h3 style="color: #06B6D4;">Mirpur</h3>
                <p style="color: #94A3B8;">Road 10, Block C<br>Mirpur, Dhaka 1216</p>
                <p style="color: #38BDF8;">📞 01712-345679</p>
            </div>
            <div style="background: #0F172A; padding: 20px; border-radius: 8px;">
                <h3 style="color: #06B6D4;">Uttara</h3>
                <p style="color: #94A3B8;">Sector 7, Road 15<br>Uttara, Dhaka 1230</p>
                <p style="color: #38BDF8;">📞 01712-345680</p>
            </div>
        </div>
    </div>

    <!-- Our Team -->
    <div class="card">
        <h2 style="color: #38BDF8; margin-bottom: 30px;">👥 Our Leadership Team</h2>
        <div class="team-grid">
            <div class="team-member">
                <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="Principal">
                <div class="member-name">Prof. Dr. Abdur Rahman</div>
                <div class="member-role">Founder & Principal</div>
                <p style="color: #94A3B8; font-size: 14px;">PhD in Education, 25+ years experience</p>
            </div>
            <div class="team-member">
                <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="Academic Head">
                <div class="member-name">Dr. Fatema Begum</div>
                <div class="member-role">Academic Director</div>
                <p style="color: #94A3B8; font-size: 14px;">M.Ed, Curriculum Specialist</p>
            </div>
            <div class="team-member">
                <img src="https://randomuser.me/api/portraits/men/46.jpg" alt="Admin Head">
                <div class="member-name">Md. Shahidul Islam</div>
                <div class="member-role">Administrative Head</div>
                <p style="color: #94A3B8; font-size: 14px;">MBA, Operations Expert</p>
            </div>
        </div>
    </div>

    <!-- Contact Info -->
    <div class="card">
        <h2 style="color: #38BDF8; margin-bottom: 20px;">📞 Get in Touch</h2>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; text-align: center;">
            <div>
                <h3 style="color: #06B6D4;">📱 Phone</h3>
                <p style="color: #94A3B8;">+880 2987<br>+880 1712-345678</p>
            </div>
            <div>
                <h3 style="color: #06B6D4;">✉️ Email</h3>
                <p style="color: #94A3B8;">info@coachingpro.com<br>admissions@coachingpro.com</p>
            </div>
            <div>
                <h3 style="color: #06B6D4;">🕒 Hours</h3>
                <p style="color: #94A3B8;">Sat-Thu: 9am - 8pm<br>Fri: Closed</p>
            </div>
        </div>
    </div>

    <div style="text-align: center;">
        <a href="index.php" class="back-btn">⬅ Back to Home</a>
    </div>

</div>

<footer>
    © <?php echo date("Y"); ?> Coaching Pro | All Rights Reserved
</footer>

</body>
</html>