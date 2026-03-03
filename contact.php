<?php
// contact.php - Contact Us Page

// Handle form submission (optional)
$message_sent = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    
    // Here you can add code to send email or save to database
    $message_sent = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Us | Coaching Pro</title>
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

        .contact-grid{
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .contact-info{
            background: #1E293B;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }

        .contact-info h2{
            color: #38BDF8;
            border-bottom: 2px solid #06B6D4;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }

        .info-item{
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding: 10px;
            background: #0F172A;
            border-radius: 8px;
            transition: 0.3s;
        }

        .info-item:hover{
            transform: translateX(5px);
        }

        .info-icon{
            width: 50px;
            height: 50px;
            background: #06B6D4;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }

        .info-text h3{
            margin: 0 0 5px 0;
            color: #38BDF8;
        }

        .info-text p{
            margin: 0;
            color: #94A3B8;
        }

        .contact-form{
            background: #1E293B;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }

        .contact-form h2{
            color: #38BDF8;
            border-bottom: 2px solid #06B6D4;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }

        .form-group{
            margin-bottom: 20px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select{
            width: 100%;
            padding: 12px;
            border: 2px solid #334155;
            background: #0F172A;
            color: #fff;
            border-radius: 8px;
            font-size: 16px;
            transition: 0.3s;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus{
            border-color: #06B6D4;
            outline: none;
        }

        .form-group input::placeholder,
        .form-group textarea::placeholder{
            color: #64748B;
        }

        .form-row{
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .submit-btn{
            background: #06B6D4;
            color: #000;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            width: 100%;
        }

        .submit-btn:hover{
            background: #38BDF8;
            transform: translateY(-2px);
        }

        .success-message{
            background: #059669;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .branch-map{
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .branch-card{
            background: #1E293B;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
            transition: 0.3s;
        }

        .branch-card:hover{
            transform: translateY(-5px);
        }

        .branch-card h3{
            color: #06B6D4;
            margin-bottom: 10px;
        }

        .branch-card p{
            color: #94A3B8;
            margin: 5px 0;
        }

        .branch-btn{
            display: inline-block;
            margin-top: 10px;
            padding: 8px 15px;
            background: transparent;
            border: 2px solid #06B6D4;
            color: #06B6D4;
            text-decoration: none;
            border-radius: 5px;
            font-size: 13px;
            transition: 0.3s;
        }

        .branch-btn:hover{
            background: #06B6D4;
            color: #000;
        }

        .social-links{
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 30px 0;
        }

        .social-link{
            width: 50px;
            height: 50px;
            background: #1E293B;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #06B6D4;
            text-decoration: none;
            font-size: 20px;
            transition: 0.3s;
        }

        .social-link:hover{
            background: #06B6D4;
            color: #000;
            transform: translateY(-3px);
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
            .contact-grid{
                grid-template-columns: 1fr;
            }
            .form-row{
                grid-template-columns: 1fr;
            }
            .branch-map{
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <h1>📞 Contact Coaching Pro</h1>
    <p class="subtitle">We're Here to Help • Get in Touch With Us</p>

    <!-- Success Message -->
    <?php if($message_sent): ?>
    <div class="success-message">
        ✅ Thank you for contacting us! We'll get back to you soon.
    </div>
    <?php endif; ?>

    <!-- Contact Grid -->
    <div class="contact-grid">
        <!-- Contact Information -->
        <div class="contact-info">
            <h2>📍 Get in Touch</h2>
            
            <div class="info-item">
                <div class="info-icon">📍</div>
                <div class="info-text">
                    <h3>Main Office</h3>
                    <p>House 45, Road 27, Dhanmondi<br>Dhaka 1205, Bangladesh</p>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">📞</div>
                <div class="info-text">
                    <h3>Phone Numbers</h3>
                    <p>Hotline: 2987<br>Mobile: +880 1712-345678</p>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">✉️</div>
                <div class="info-text">
                    <h3>Email Addresses</h3>
                    <p>info@coachingpro.com<br>admissions@coachingpro.com</p>
                </div>
            </div>

            <div class="info-item">
                <div class="info-icon">🕒</div>
                <div class="info-text">
                    <h3>Office Hours</h3>
                    <p>Saturday - Thursday: 9:00 AM - 8:00 PM<br>Friday: Closed</p>
                </div>
            </div>
        </div>

        <!-- Contact Form -->
        <div class="contact-form">
            <h2>✉️ Send us a Message</h2>
            
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <input type="text" name="name" placeholder="Your Full Name *" required>
                    </div>
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Your Email *" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <input type="tel" name="phone" placeholder="Your Phone Number">
                    </div>
                    <div class="form-group">
                        <select name="subject">
                            <option value="">Select Subject</option>
                            <option value="admission">Admission Inquiry</option>
                            <option value="class">Class 9/10 Information</option>
                            <option value="ssc">SSC Batch 2026</option>
                            <option value="fees">Fees & Payment</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <textarea name="message" rows="5" placeholder="Your Message *" required></textarea>
                </div>

                <button type="submit" class="submit-btn">📨 Send Message</button>
            </form>
        </div>
    </div>

    <!-- Our Branches -->
    <h2 style="color: #38BDF8; margin: 40px 0 20px; text-align: center;">🏢 Our Branches</h2>
    <div class="branch-map">
        <div class="branch-card">
            <h3>Dhanmondi Branch</h3>
            <p>📍 Road 27, House 45</p>
            <p>Dhanmondi, Dhaka 1205</p>
            <p>📞 01712-345678</p>
            <a href="https://www.google.com/maps?q=23.7465,90.3756" target="_blank" class="branch-btn">View on Map</a>
        </div>
        
        <div class="branch-card">
            <h3>Mirpur Branch</h3>
            <p>📍 Road 10, Block C</p>
            <p>Mirpur, Dhaka 1216</p>
            <p>📞 01712-345679</p>
            <a href="https://www.google.com/maps?q=23.8065,90.3650" target="_blank" class="branch-btn">View on Map</a>
        </div>
        
        <div class="branch-card">
            <h3>Uttara Branch</h3>
            <p>📍 Sector 7, Road 15</p>
            <p>Uttara, Dhaka 1230</p>
            <p>📞 01712-345680</p>
            <a href="https://www.google.com/maps?q=23.8765,90.3950" target="_blank" class="branch-btn">View on Map</a>
        </div>
    </div>

    <!-- Social Media Links -->
    <div class="social-links">
        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
        <a href="#" class="social-link"><i class="fab fa-youtube"></i></a>
    </div>

    <!-- FAQ Section -->
    <div style="background: #1E293B; padding: 30px; border-radius: 12px; margin: 40px 0;">
        <h2 style="color: #38BDF8; margin-bottom: 20px;">❓ Frequently Asked Questions</h2>
        
        <div style="margin-bottom: 15px;">
            <h3 style="color: #06B6D4;">Q: How can I enroll in Class 9?</h3>
            <p style="color: #94A3B8;">A: You can visit any of our branches or fill the contact form above. Our admission team will contact you within 24 hours.</p>
        </div>
        
        <div style="margin-bottom: 15px;">
            <h3 style="color: #06B6D4;">Q: What are the class timings?</h3>
            <p style="color: #94A3B8;">A: Regular classes run from 2:20 PM to 6:45 PM (Saturday-Thursday). Please check the Class 9 routine for detailed schedule.</p>
        </div>
        
        <div style="margin-bottom: 15px;">
            <h3 style="color: #06B6D4;">Q: Is there a free trial class?</h3>
            <p style="color: #94A3B8;">A: Yes! We offer 2 free trial classes. Click the "Free Class" button on our homepage.</p>
        </div>
        
        <div>
            <h3 style="color: #06B6D4;">Q: How can I pay the fees?</h3>
            <p style="color: #94A3B8;">A: You can pay via cash, bKash (01712-345678), or bank transfer. Monthly fees are 2500 BDT for all subjects.</p>
        </div>
    </div>

    <!-- Map Embed -->
    <div style="background: #1E293B; padding: 20px; border-radius: 12px; margin: 40px 0;">
        <h2 style="color: #38BDF8; margin-bottom: 20px; text-align: center;">🗺️ Our Location</h2>
        <iframe 
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3652.4184!2d90.3756!3d23.7465!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3755b8b33e3c3c3d%3A0x3c3d3c3d3c3d3c3d!2sDhanmondi%2C%20Dhaka!5e0!3m2!1sen!2sbd!4v1234567890" 
            width="100%" 
            height="400" 
            style="border:0; border-radius: 8px;" 
            allowfullscreen="" 
            loading="lazy">
        </iframe>
    </div>

    <div style="text-align: center;">
        <a href="index.php" class="back-btn">⬅ Back to Home</a>
    </div>

</div>

<footer>
    © <?php echo date("Y"); ?> Coaching Pro | All Rights Reserved
</footer>

<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</body>
</html>