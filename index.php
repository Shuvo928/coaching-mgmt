<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coaching Pro | Class 9-10 & SSC Preparation</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts - Inter (clean, professional) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
    
    <style>
        /* Coaching Pro Specific Styles */
        :root {
            --navy: #0F172A;
            --cyan: #06B6D4;
            --light-cyan: #38BDF8;
            --light-bg: #F8FAFC;
            --text-dark: #111827;
            --white: #FFFFFF;
            --border-radius: 12px;
            --border-radius-sm: 8px;
            --box-shadow: 0 10px 30px -10px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--text-dark);
            background: var(--white);
            overflow-x: hidden;
        }

        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 700;
            color: var(--text-dark);
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .section-subtitle {
            font-size: 1.125rem;
            color: #4B5563;
            margin-bottom: 3rem;
        }

        /* Container */
        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Section Padding */
        .section-padding {
            padding: 80px 0;
        }

        /* Buttons */
        .btn-cyan {
            background: var(--cyan);
            color: var(--white);
            padding: 12px 30px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            border: 2px solid var(--cyan);
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }

        .btn-cyan:hover {
            background: var(--light-cyan);
            border-color: var(--light-cyan);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
            color: var(--white);
        }

        .btn-outline-cyan {
            background: transparent;
            color: var(--cyan);
            padding: 12px 30px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            border: 2px solid var(--cyan);
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline-cyan:hover {
            background: var(--cyan);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }

        /* Navbar */
        .navbar {
            background: var(--white);
            padding: 20px 0;
            transition: var(--transition);
            box-shadow: none;
        }

        .navbar.scrolled {
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            padding: 15px 0;
        }

        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--navy) !important;
            letter-spacing: -0.5px;
        }

        .navbar-nav .nav-link {
            font-weight: 500;
            color: var(--text-dark) !important;
            margin: 0 12px;
            position: relative;
            padding: 5px 0;
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--cyan);
            transition: width 0.3s ease;
        }

        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }

        .navbar-nav .nav-link:hover {
            color: var(--cyan) !important;
        }

        .phone-number {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-dark);
            font-weight: 600;
            margin-right: 20px;
        }

        .phone-number i {
            color: var(--cyan);
            font-size: 1.2rem;
        }

        /* Hero Section */
        .hero-section {
            background: var(--navy);
            padding: 120px 0 80px;
            position: relative;
            overflow: hidden;
        }

        .hero-badge {
            background: var(--cyan);
            color: var(--white);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 20px;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--white);
            line-height: 1.2;
            margin-bottom: 30px;
        }

        .hero-title span {
            color: var(--cyan);
        }

        /* smaller line underneath main hero text */
        .hero-title .sub-line {
            font-size: 2rem; /* adjust as needed */
            display: block;
            line-height: 1.3;
        }

        .class-selector {
            display: flex;
            gap: 15px;
            margin: 30px 0;
            flex-wrap: wrap;
        }

        .class-card {
            background: var(--white);
            padding: 15px 25px;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            font-weight: 600;
            color: var(--navy);
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .class-card:hover {
            transform: translateY(-3px);
            border-color: var(--cyan);
            box-shadow: 0 15px 30px rgba(6, 182, 212, 0.15);
        }

        .hero-illustration {
            animation: float 6s ease-in-out infinite;
            /* ensure the container sits above any background elements */
            position: relative;
            z-index: 1;
        }

        /* video inside hero can be styled independently */
        .hero-illustration .hero-video {
            width: 100%;
            height: auto;
            display: block;
            /* ensure pointer events work on the video itself */
            pointer-events: auto;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        /* Feature Cards */
        .feature-card {
            background: var(--white);
            padding: 40px 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
            transition: var(--transition);
            height: 100%;
            border-top: 3px solid transparent;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-top-color: var(--cyan);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .feature-icon {
            width: 70px;
            height: 70px;
            background: rgba(6, 182, 212, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            color: var(--cyan);
            font-size: 1.8rem;
        }

        /* Branch Cards */
        .branch-card {
            background: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            height: 100%;
        }

        .branch-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .branch-card h4 {
            margin-bottom: 15px;
            color: var(--navy);
        }

        .branch-card p {
            color: #4B5563;
            margin-bottom: 25px;
        }

        /* Program Cards */
        .program-card {
            background: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .program-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .program-content {
            padding: 30px;
            flex: 1;
        }

        .program-content h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .program-content p {
            color: #4B5563;
            margin-bottom: 25px;
        }

        .program-btn {
            background: transparent;
            color: var(--cyan);
            border: 2px solid var(--cyan);
            padding: 10px 20px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: var(--transition);
        }

        .program-btn:hover {
            background: var(--cyan);
            color: var(--white);
        }

        .program-illustration {
            padding: 20px;
            text-align: center;
            background: rgba(6, 182, 212, 0.05);
        }

        /* Stats Section */
        .stats-section {
            background: var(--navy);
            color: var(--white);
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--cyan);
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1.125rem;
            opacity: 0.9;
        }

        /* Testimonial Cards */
        .testimonial-card {
            background: var(--white);
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            height: 100%;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
        }

        .testimonial-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .testimonial-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        .testimonial-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .testimonial-role {
            font-size: 0.875rem;
            color: #6B7280;
        }

        .testimonial-text {
            color: #4B5563;
            font-style: italic;
            line-height: 1.7;
        }

        /* CTA Section */
        .cta-section {
            background: var(--navy);
            padding: 80px 0;
            text-align: center;
        }

        .cta-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--white);
            margin-bottom: 30px;
        }

        .cta-button {
            background: var(--cyan);
            color: var(--white);
            padding: 15px 40px;
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            font-size: 1.125rem;
            border: none;
            transition: var(--transition);
            box-shadow: 0 0 20px rgba(6, 182, 212, 0.5);
        }

        .cta-button:hover {
            background: var(--light-cyan);
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(6, 182, 212, 0.7);
        }

        /* Footer */
        .footer {
            background: var(--navy);
            color: var(--white);
            padding: 60px 0 20px;
        }

        .footer h5 {
            color: var(--white);
            font-weight: 600;
            margin-bottom: 20px;
        }

        .footer ul {
            list-style: none;
            padding: 0;
        }

        .footer ul li {
            margin-bottom: 10px;
        }

        .footer ul li a {
            color: #9CA3AF;
            text-decoration: none;
            transition: var(--transition);
        }

        .footer ul li a:hover {
            color: var(--cyan);
        }

        .footer-contact {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .footer-contact i {
            color: var(--cyan);
            width: 20px;
        }

        .copyright {
            text-align: center;
            padding-top: 40px;
            margin-top: 40px;
            border-top: 1px solid #1E293B;
            color: #9CA3AF;
            font-size: 0.875rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .class-selector {
                flex-direction: column;
            }
            
            .class-card {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                Coaching<span style="color: var(--cyan);">Pro</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#">Class 9</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Class 10</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">SSC Batch </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Contact</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="phone-number">
                        <i class="fas fa-phone-alt"></i>
                        <span>2987</span>
                    </div>
                    <a href="admin/login.php" class="btn-outline-cyan me-2">Login</a>
                    <a href="#" class="btn-cyan">Admission</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-5" data-aos="fade-right">
                    
                    <h1 class="hero-title">
    Let learning be fun!<br>
    <span class="sub-line">Complete preparation<br>at your own pace</span>
</h1>
                    
                    <div class="class-selector">
                        <div class="class-card">SSC Batch </div>
                        <div class="class-card">Class 9</div>
                        <div class="class-card">Class 10</div>
                    </div>
                    
                </div>
                <div class="col-lg-7" data-aos="fade-left">
                    <div class="hero-illustration">
                        <!-- added controls and custom class for additional styling; video remains muted for autoplay support -->
                        <video class="hero-video img-fluid" controls autoplay loop muted playsinline>
                            <source src="assets/videos/1422633-hd_1920_810_24fps.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Learn Online Section -->
    <section class="section-padding">
        <div class="container">
            <div class="text-center" data-aos="fade-up">
                <h2 class="section-title">Learn at your own convenience and pace!</h2>
                <p class="section-subtitle">Access classes conducted by the country’s most experienced teachers, along with recorded lectures and uninterrupted practice facilities.</p>
            </div>
            
            <div class="row g-4 mt-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-video"></i>
                        </div>
                        <h3>Recorded Classes</h3>
                        <p class="text-secondary">Access high-quality recorded lectures anytime, anywhere with lifetime access.</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-pen"></i>
                        </div>
                        <h3>Practice & Exams</h3>
                        <p>Regular practice materials and model tests for complete exam preparation.</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h3>Expert Teachers</h3>
                        <p>Learn from the country's most experienced and qualified subject experts.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Offline Branch Section -->
    <!-- Branch Locations Section with Google Maps -->
<section class="section-padding" style="background: var(--light-bg);">
    <div class="container">
        <div class="text-center" data-aos="fade-up">
            <h2 class="section-title">Our Branch Locations</h2>
            <p class="section-subtitle">Visit our state-of-the-art learning centers across Dhaka</p>
        </div>
        
        <div class="row g-4">
            <!-- Dhanmondi Branch -->
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="branch-card">
                    <h4>Dhanmondi Branch</h4>
                    <p>Road 27, House 45<br>Dhanmondi, Dhaka 1205</p>
                    <a href="https://www.google.com/maps?q=23.7465,90.3756" 
                       target="_blank" 
                       class="btn-outline-cyan btn-sm">
                        <i class="fas fa-map-marker-alt me-2"></i>Join free of offline classes
                    </a>
                </div>
            </div>
            
            <!-- Mirpur Branch -->
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="branch-card">
                    <h4>Mirpur Branch</h4>
                    <p>Road 10, Block C<br>Mirpur, Dhaka 1216</p>
                    <a href="https://www.google.com/maps?q=23.8065,90.3650" 
                       target="_blank" 
                       class="btn-outline-cyan btn-sm">
                        <i class="fas fa-map-marker-alt me-2"></i>Join free of offline classes
                    </a>
                </div>
            </div>
            
            <!-- Uttara Branch -->
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="branch-card">
                    <h4>Uttara Branch</h4>
                    <p>Sector 7, Road 15<br>Uttara, Dhaka 1230</p>
                    <a href="https://www.google.com/maps?q=23.8765,90.3950" 
                       target="_blank" 
                       class="btn-outline-cyan btn-sm">
                        <i class="fas fa-map-marker-alt me-2"></i>Join free of offline classes
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
    <!-- Why Choose Us Section -->
    <section class="section-padding" style="background: var(--navy); color: var(--white);">
        <div class="container">
            <div class="text-center" data-aos="fade-up">
                <h2 class="section-title" style="color: var(--white);">Why Students Choose Coaching Pro</h2>
                <div style="width: 80px; height: 3px; background: var(--cyan); margin: 20px auto;"></div>
            </div>
            
            <div class="row g-4 mt-4">
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="text-center">
                        <i class="fas fa-book-open fa-3x mb-3" style="color: var(--cyan);"></i>
                        <h4 style="color: var(--white);">Structured Curriculum</h4>
                        <p style="color: #9CA3AF;">Board-aligned syllabus with systematic progression</p>
                    </div>
                </div>
                
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="text-center">
                        <i class="fas fa-chart-line fa-3x mb-3" style="color: var(--cyan);"></i>
                        <h4 style="color: var(--white);">Smart Progress Tracking</h4>
                        <p style="color: #9CA3AF;">Real-time performance analytics and insights</p>
                    </div>
                </div>
                
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="text-center">
                        <i class="fas fa-headset fa-3x mb-3" style="color: var(--cyan);"></i>
                        <h4 style="color: var(--white);">Live + Recorded Support</h4>
                        <p style="color: #9CA3AF;">24/7 access to learning materials and support</p>
                    </div>
                </div>
                
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="400">
                    <div class="text-center">
                        <i class="fas fa-chart-pie fa-3x mb-3" style="color: var(--cyan);"></i>
                        <h4 style="color: var(--white);">Performance Analytics</h4>
                        <p style="color: #9CA3AF;">Detailed reports and improvement tracking</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Programs Section -->
    <section class="section-padding">
        <div class="container">
            <div class="text-center" data-aos="fade-up">
                <h2 class="section-title">Programs We Offer</h2>
                <p class="section-subtitle">Focused preparation for academic excellence</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="program-card">
                        <div class="program-content">
                            <h3>SSC Batch</h3>
                            <p>Complete Board Preparation with intensive revision and model tests</p>
                            <a href="#" class="program-btn">View Details →</a>
                        </div>
                        <div class="program-illustration">
                            <!-- use local upload instead of external illustration -->
                            <img src="uploads/images.png" alt="SSC" height="150">
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="program-card">
                        <div class="program-content">
                            <h3>Class 10</h3>
                            <p>Full Academic Support with chapter-wise preparation and exams</p>
                            <a href="#" class="program-btn">View Details →</a>
                        </div>
                        <div class="program-illustration">
                            <img src="uploads/download.jpeg" alt="Class 10" height="150">
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="program-card">
                        <div class="program-content">
                            <h3>Class 9</h3>
                            <p>Foundation & Exam Strategy for building strong academic base</p>
                            <a href="#" class="program-btn">View Details →</a>
                        </div>
                        <div class="program-illustration">
                            <img src="uploads/1571659996.png" alt="Class 9" height="150">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <!-- Statistics Section with Formatted Live Counting -->
<section class="stats-section section-padding">
    <div class="container">
        <div class="row">
            <div class="col-md-3 col-6 mb-4" data-aos="zoom-in" data-aos-delay="100">
                <div class="stat-item">
                    <div class="stat-number counter" data-target="1200">0</div>
                    <div class="stat-label">Students Enrolled</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-4" data-aos="zoom-in" data-aos-delay="200">
                <div class="stat-item">
                    <div class="stat-number counter" data-target="50">0</div>
                    <div class="stat-label">Expert Teachers</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-4" data-aos="zoom-in" data-aos-delay="300">
                <div class="stat-item">
                    <div class="stat-number counter" data-target="95">0</div>
                    <div class="stat-label">Success Rate</div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-4" data-aos="zoom-in" data-aos-delay="400">
                <div class="stat-item">
                    <div class="stat-number counter" data-target="10000">0</div>
                    <div class="stat-label">Practice Questions</div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Advanced Counter with Number Formatting
(function() {
    // Format number with commas
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    // Animate counter with formatting
    function animateCounter(counter) {
        const target = parseInt(counter.getAttribute('data-target'));
        const duration = 2000; // 2 seconds
        const startTime = performance.now();
        const startValue = 0;
        
        function updateCounter(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function for smooth animation
            const easeOutExpo = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
            const currentValue = Math.floor(startValue + (target - startValue) * easeOutExpo);
            
            // Format number with commas
            counter.innerText = formatNumber(currentValue);
            
            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            } else {
                counter.innerText = formatNumber(target);
            }
        }
        
        requestAnimationFrame(updateCounter);
    }

    // Set up intersection observer with better options
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const counter = entry.target;
                if (!counter.classList.contains('counting')) {
                    counter.classList.add('counting');
                    
                    // Small random delay for staggered effect
                    const delay = Math.random() * 300;
                    setTimeout(() => {
                        animateCounter(counter);
                    }, delay);
                }
                observer.unobserve(counter);
            }
        });
    }, {
        threshold: 0.3,
        rootMargin: '50px'
    });

    // Start observing after page load
    document.addEventListener('DOMContentLoaded', function() {
        const counters = document.querySelectorAll('.counter');
        counters.forEach(counter => {
            // Store initial value
            const target = counter.getAttribute('data-target');
            counter.setAttribute('data-target', target);
            observer.observe(counter);
        });
    });

    // Reset counters if needed (for single page applications)
    window.resetCounters = function() {
        const counters = document.querySelectorAll('.counter');
        counters.forEach(counter => {
            counter.classList.remove('counting');
            counter.innerText = '0';
            observer.observe(counter);
        });
    };
})();
</script>



    <!-- Testimonials Section -->
    <section class="section-padding" style="background: var(--light-bg);">
        <div class="container">
            <div class="text-center" data-aos="fade-up">
                <h2 class="section-title">What Parents & Students Say</h2>
                <p class="section-subtitle">Real experiences from our community</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="testimonial-card">
                        <div class="testimonial-header">
                            <img src="https://randomuser.me/api/portraits/women/44.jpg" class="testimonial-image" alt="Parent">
                            <div>
                                <div class="testimonial-name">Nasrin Akter</div>
                                <div class="testimonial-role">Parent of Class 10 Student</div>
                            </div>
                        </div>
                        <p class="testimonial-text">"The structured approach and regular progress updates have helped my daughter improve significantly. Very satisfied with the teaching quality."</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-card">
                        <div class="testimonial-header">
                            <img src="https://randomuser.me/api/portraits/men/32.jpg" class="testimonial-image" alt="Student">
                            <div>
                                <div class="testimonial-name">Rafiq Islam</div>
                                <div class="testimonial-role">SSC Candidate 2026</div>
                            </div>
                        </div>
                        <p class="testimonial-text">"The recorded lectures are a lifesaver. I can revise anytime I want. The practice materials are very helpful for exam preparation."</p>
                    </div>
                </div>
                
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="testimonial-card">
                        <div class="testimonial-header">
                            <img src="https://randomuser.me/api/portraits/women/68.jpg" class="testimonial-image" alt="Parent">
                            <div>
                                <div class="testimonial-name">Shahinur Rahman</div>
                                <div class="testimonial-role">Parent of Class 9 Student</div>
                            </div>
                        </div>
                        <p class="testimonial-text">"Excellent teachers and well-organized curriculum. My son's confidence has increased tremendously. Highly recommended!"</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2 class="cta-title" data-aos="fade-up">Start Your SSC Preparation Today</h2>
            <button class="cta-button" data-aos="fade-up" data-aos-delay="200">
                Enroll in Free Class
            </button>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>Programs</h5>
                    <ul>
                        <li><a href="#">SSC Batch 2026</a></li>
                        <li><a href="#">Class 10 Program</a></li>
                        <li><a href="#">Class 9 Program</a></li>
                        <li><a href="#">Admission Info</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>About</h5>
                    <ul>
                        <li><a href="#">Our Story</a></li>
                        <li><a href="#">Teachers</a></li>
                        <li><a href="#">Branches</a></li>
                        <li><a href="#">Careers</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>Contact</h5>
                    <ul>
                        <li class="footer-contact">
                            <i class="fas fa-phone"></i>
                            <span>2987</span>
                        </li>
                        <li class="footer-contact">
                            <i class="fas fa-envelope"></i>
                            <span>info@coachingpro.com</span>
                        </li>
                        <li class="footer-contact">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Dhaka, Bangladesh</span>
                        </li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5>Support</h5>
                    <ul>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Technical Support</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="copyright">
                © 2026 Coaching Pro. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/custom.js"></script>
    
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Counter Animation
        const counters = document.querySelectorAll('.counter');
        const speed = 200;

        counters.forEach(counter => {
            const updateCount = () => {
                const target = parseInt(counter.getAttribute('data-target'));
                const count = parseInt(counter.innerText);
                const increment = Math.ceil(target / speed);

                if (count < target) {
                    counter.innerText = count + increment;
                    setTimeout(updateCount, 20);
                } else {
                    counter.innerText = target;
                }
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        updateCount();
                        observer.unobserve(entry.target);
                    }
                });
            });

            observer.observe(counter);
        });
    </script>
    <!-- Interactive Features -->
<!-- Particles.js Library -->
<script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
<!-- Vanilla Tilt for 3D effect -->
<script src="https://cdn.jsdelivr.net/npm/vanilla-tilt@1.7.0/dist/vanilla-tilt.min.js"></script>
<!-- Smooth Scroll -->
<script src="https://cdn.jsdelivr.net/gh/cferdinandi/smooth-scroll@15/dist/smooth-scroll.polyfills.min.js"></script>

<style>
/* Floating WhatsApp & Chat Bubble */
.floating-widget {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.whatsapp-button {
    width: 60px;
    height: 60px;
    background: #25D366;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 30px;
    box-shadow: 0 10px 25px rgba(37, 211, 102, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
    animation: pulse 2s infinite;
    position: relative;
}

.whatsapp-button:hover {
    transform: scale(1.1) rotate(5deg);
    box-shadow: 0 15px 35px rgba(37, 211, 102, 0.6);
}

.whatsapp-button .tooltip {
    position: absolute;
    right: 70px;
    background: white;
    color: #333;
    padding: 8px 15px;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.whatsapp-button:hover .tooltip {
    opacity: 1;
    visibility: visible;
    right: 80px;
}

.chat-bubble {
    background: var(--cyan);
    color: white;
    padding: 15px 20px;
    border-radius: 30px 30px 30px 5px;
    max-width: 250px;
    font-size: 14px;
    box-shadow: 0 10px 25px rgba(6, 182, 212, 0.3);
    animation: float 3s ease-in-out infinite;
    position: relative;
    margin-left: auto;
}

.chat-bubble::before {
    content: '';
    position: absolute;
    bottom: 0;
    right: -10px;
    width: 20px;
    height: 20px;
    background: var(--cyan);
    clip-path: polygon(0 0, 100% 0, 100% 100%);
    border-radius: 0 0 10px 0;
}

.chat-bubble .close-btn {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 22px;
    height: 22px;
    background: white;
    color: var(--cyan);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.chat-bubble:hover .close-btn {
    opacity: 1;
}

/* Particle Background */
#particles-js {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -1;
    opacity: 0.5;
    pointer-events: none;
}

/* 3D Tilt Elements */
.tilt-3d {
    transform-style: preserve-3d;
    transition: transform 0.1s;
}

.feature-card.tilt-3d,
.program-card.tilt-3d,
.branch-card.tilt-3d,
.testimonial-card.tilt-3d {
    will-change: transform;
}

/* Animations */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); }
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

/* Smooth Scroll Progress Bar */
.scroll-progress {
    position: fixed;
    top: 0;
    left: 0;
    width: 0%;
    height: 3px;
    background: linear-gradient(90deg, var(--cyan), var(--light-cyan));
    z-index: 99999;
    transition: width 0.1s ease;
    box-shadow: 0 0 10px var(--cyan);
}

/* Loading Animation */
.loading-spinner {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 50px;
    height: 50px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid var(--cyan);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    z-index: 99999;
    display: none;
}

@keyframes spin {
    0% { transform: translate(-50%, -50%) rotate(0deg); }
    100% { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Page Transition */
.fade-in {
    animation: fadeIn 1s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<!-- Scroll Progress Bar -->
<div class="scroll-progress"></div>

<!-- Loading Spinner -->
<div class="loading-spinner" id="loadingSpinner"></div>

<!-- Floating Widgets -->
<div class="floating-widget">
    <div class="chat-bubble" id="chatBubble">
        <span class="close-btn" onclick="this.parentElement.remove()">✕</span>
        👋 Need help? Chat with us!
        <div style="font-size: 11px; margin-top: 5px; opacity: 0.8;">Usually replies instantly</div>
    </div>
    <div class="whatsapp-button" onclick="window.open('https://wa.me/8802987?text=Hi%20Coaching%20Pro,%20I%20need%20help%20with...', '_blank')">
        <i class="fab fa-whatsapp"></i>
        <span class="tooltip">Chat on WhatsApp</span>
    </div>
</div>

<!-- Particle Background Container -->
<div id="particles-js"></div>

<script>
// ============================================
// 1. ANIMATED PARTICLE BACKGROUND
// ============================================
particlesJS('particles-js', {
    particles: {
        number: { value: 80, density: { enable: true, value_area: 800 } },
        color: { value: '#06B6D4' },
        shape: { type: 'circle' },
        opacity: { value: 0.5, random: false, anim: { enable: false } },
        size: { value: 3, random: true, anim: { enable: false } },
        line_linked: { enable: true, distance: 150, color: '#06B6D4', opacity: 0.2, width: 1 },
        move: { enable: true, speed: 2, direction: 'none', random: false, straight: false, out_mode: 'out', bounce: false }
    },
    interactivity: {
        detect_on: 'canvas',
        events: {
            onhover: { enable: true, mode: 'repulse' },
            onclick: { enable: true, mode: 'push' },
            resize: true
        },
        modes: {
            repulse: { distance: 100, duration: 0.4 },
            push: { particles_nb: 4 }
        }
    },
    retina_detect: true
});

// ============================================
// 2. 3D MOUSE TILT INTERACTION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Apply tilt to all cards
    const tiltElements = document.querySelectorAll('.feature-card, .program-card, .branch-card, .testimonial-card, .class-card');
    
    tiltElements.forEach(element => {
        element.classList.add('tilt-3d');
        
        element.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 20;
            const rotateY = (centerX - x) / 20;
            
            this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02)`;
        });
        
        element.addEventListener('mouseleave', function() {
            this.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale(1)';
        });
    });

    // VanillaTilt as backup (more performant)
    if (typeof VanillaTilt !== 'undefined') {
        VanillaTilt.init(tiltElements, {
            max: 15,
            speed: 400,
            glare: true,
            'max-glare': 0.3,
            scale: 1.02
        });
    }
});

// ============================================
// 3. SMOOTH SCROLL ANIMATION
// ============================================
// Initialize Smooth Scroll
var scroll = new SmoothScroll('a[href*="#"]', {
    speed: 800,
    speedAsDuration: true,
    easing: 'easeInOutCubic',
    offset: 80,
    updateURL: true
});

// Scroll progress bar
window.addEventListener('scroll', function() {
    const winScroll = document.body.scrollTop || document.documentElement.scrollTop;
    const height = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    const scrolled = (winScroll / height) * 100;
    document.querySelector('.scroll-progress').style.width = scrolled + '%';
});

// ============================================
// 4. ADDITIONAL INTERACTIVE FEATURES
// ============================================

// Page transition animation
document.body.classList.add('fade-in');

// Loading spinner on link clicks
document.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', function(e) {
        if (!this.href.includes('#') && !this.href.includes('javascript')) {
            document.getElementById('loadingSpinner').style.display = 'block';
        }
    });
});

// Auto-hide loading spinner after page load
window.addEventListener('load', function() {
    document.getElementById('loadingSpinner').style.display = 'none';
});

// Parallax effect on hero section
window.addEventListener('scroll', function() {
    const scrolled = window.pageYOffset;
    const hero = document.querySelector('.hero-section');
    if (hero) {
        hero.style.transform = `translateY(${scrolled * 0.3}px)`;
        hero.style.opacity = 1 - (scrolled / 700);
    }
});

// Typewriter effect for hero title
function typeWriter(element, text, speed = 100) {
    let i = 0;
    element.innerHTML = '';
    function type() {
        if (i < text.length) {

// toggle play/pause when user taps/clicks the hero video
document.addEventListener('DOMContentLoaded', () => {
    const heroVid = document.querySelector('.hero-illustration video');
    if (heroVid) {
        heroVid.addEventListener('click', () => {
            if (heroVid.paused) heroVid.play();
            else heroVid.pause();
        });
    }
});
            element.innerHTML += text.charAt(i);
            i++;
            setTimeout(type, speed);
        }
    }
    type();
}

// Uncomment to enable typewriter effect
// const heroTitle = document.querySelector('.hero-title');
// if (heroTitle) {
//     const originalText = heroTitle.innerText;
//     typeWriter(heroTitle, originalText, 50);
// }

// Auto-dismiss chat bubble after 10 seconds
setTimeout(() => {
    const chatBubble = document.getElementById('chatBubble');
    if (chatBubble) {
        chatBubble.style.transition = 'opacity 0.5s';
        chatBubble.style.opacity = '0';
        setTimeout(() => chatBubble.remove(), 500);
    }
}, 10000);

// Easter egg - Konami code
let konamiCode = [];
const konamiSequence = [38, 38, 40, 40, 37, 39, 37, 39, 66, 65];
document.addEventListener('keydown', function(e) {
    konamiCode.push(e.keyCode);
    if (konamiCode.length > konamiSequence.length) {
        konamiCode.shift();
    }
    if (konamiCode.join(',') === konamiSequence.join(',')) {
        document.body.style.background = 'linear-gradient(45deg, #ff6b6b, #4ecdc4)';
        alert('🎮 Konami Code Activated!');
    }
});
</script>

<!-- Add Font Awesome if not already included -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>