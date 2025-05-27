<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Chino Car Parking Solution</title>
    <link rel="stylesheet" href="custom.css" />
    <style>
        /* Reset and base styles */
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #212529;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 960px;
            margin: 2rem auto;
            background: white;
            border-radius: 0.5rem;
            padding: 2rem 3rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.75rem;
            color: #2563eb;
            text-decoration: none;
        }
        nav a {
            color: #495057;
            text-decoration: none;
            margin-left: 1.5rem;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        nav a:hover {
            color: #2563eb;
        }
        .hero {
            text-align: center;
            padding: 4rem 2rem 3rem 2rem;
        }
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #2563eb;
        }
        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            color: #495057;
        }
        .btn-primary {
            background-color: #2563eb;
            color: white;
            padding: 0.75rem 1.5rem;
            font-size: 1.25rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 2rem;
        }
        .btn-primary:hover {
            background-color: #1e40af;
        }
        .map-container {
            text-align: center;
            margin: 2rem 0;
        }
        iframe {
            border-radius: 0.5rem;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .features, .testimonials, .hours-pricing, .faq, .social {
            max-width: 960px;
            margin: 3rem auto;
            background: white;
            border-radius: 0.5rem;
            padding: 2rem 3rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            color: #212529;
        }
        .features h3, .testimonials h3, .hours-pricing h3, .faq h3, .social h3 {
            margin-bottom: 1rem;
        }
        .features ul {
            list-style-type: disc;
            padding-left: 1.5rem;
        }
        .testimonials blockquote {
            font-style: italic;
            margin: 1rem 0;
            border-left: 4px solid #2563eb;
            padding-left: 1rem;
            color: #495057;
        }
        .faq dt {
            font-weight: 600;
            margin-top: 1rem;
        }
        .faq dd {
            margin-left: 1rem;
            margin-bottom: 1rem;
        }
        .social a {
            color: #2563eb;
            margin-right: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .social a:hover {
            color: #1e40af;
        }
        .contact {
            background: white;
            color: #2563eb;
            padding: 2rem 3rem;
            border-radius: 0.5rem;
            max-width: 400px;
            margin: 3rem auto 2rem auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            font-size: 1.25rem;
            font-weight: 600;
            text-align: center;
        }
        footer {
            text-align: center;
            padding: 1rem;
            color: #6c757d;
            font-size: 0.9rem;
            border-top: 1px solid #dee2e6;
            margin-top: 2rem;
        }
        @media (max-width: 900px) {
            .container {
                padding: 1.5rem 1.5rem;
                margin: 1rem;
            }
            .features, .testimonials, .hours-pricing, .faq, .social {
                padding: 1.5rem 1.5rem;
                margin: 2rem 1rem;
            }
            .contact {
                margin: 2rem 1rem;
                padding: 1.5rem 1.5rem;
                max-width: 100%;
            }
            nav a {
                margin-left: 1rem;
            }
        }
        @media (max-width: 600px) {
            .hero h1 {
                font-size: 2.25rem;
            }
            .hero p {
                font-size: 1rem;
            }
            .btn-primary {
                font-size: 1rem;
                padding: 0.5rem 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <a href="#" class="navbar-brand">Chino Car Parking Solution</a>
            <nav>
                <a href="#features">Features</a>
                <a href="#testimonials">Testimonials</a>
                <a href="#hours-pricing">Hours & Pricing</a>
                <a href="#faq">FAQ</a>
                <a href="#contact">Contact</a>
            </nav>
        </header>
        <section class="hero">
            <h1>Welcome to Chino Car Parking</h1>
            <p>Parking area available at Chino Park located at Kimara, Golani Kijiweni in Dar es Salaam.</p>
            <a href="tel:+255716959578" class="btn-primary">Call to Book Now</a>
        </section>
        <section class="map-container">
            <h3>Our Location</h3>
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15955.123456789!2d39.234567!3d-6.789012!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x185c4c1234567890%3A0xabcdef1234567890!2sChino%20Park%2C%20Kimara%2C%20Dar%20es%20Salaam!5e0!3m2!1sen!2stz!4v1680000000000!5m2!1sen!2stz"
                width="100%" height="300" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </section>
        <section id="features" class="features">
            <h3>Why Choose Chino Car Parking?</h3>
            <ul>
                <li>Secure and monitored parking area</li>
                <li>Convenient location at Kimara, Golani Kijiweni</li>
                <li>Affordable pricing and flexible options</li>
                <li>24/7 customer support and assistance</li>
                <li>Easy booking and quick entry/exit process</li>
            </ul>
        </section>
        <section id="testimonials" class="testimonials">
            <h3>What Our Customers Say</h3>
            <blockquote>"Safe and reliable parking service. Highly recommend!" - John M.</blockquote>
            <blockquote>"Convenient location and friendly staff. Will use again." - Asha K.</blockquote>
            <blockquote>"Affordable and secure. Great experience overall." - Michael T.</blockquote>
        </section>
        <section id="hours-pricing" class="hours-pricing">
            <h3>Operating Hours & Pricing</h3>
            <p><strong>Operating Hours:</strong> 6:00 AM - 10:00 PM (Everyday)</p>
            <p><strong>Pricing:</strong> TZS 1,000 per hour, discounts for monthly subscriptions</p>
        </section>
        <section id="faq" class="faq">
            <h3>Frequently Asked Questions</h3>
            <dl>
                <dt>Is the parking area secure?</dt>
                <dd>Yes, we have 24/7 surveillance and security personnel on site.</dd>
                <dt>Can I reserve a parking spot in advance?</dt>
                <dd>Yes, you can call us to book your spot ahead of time.</dd>
                <dt>What payment methods do you accept?</dt>
                <dd>We accept cash, mobile money, and credit/debit cards.</dd>
            </dl>
        </section>
        <section class="social">
            <h3>Connect with Us</h3>
            <p>Follow us on social media for updates and promotions:</p>
            <p>
                <a href="#" aria-label="Facebook" title="Facebook">Facebook</a>
                <a href="#" aria-label="Twitter" title="Twitter">Twitter</a>
                <a href="#" aria-label="Instagram" title="Instagram">Instagram</a>
            </p>
        </section>
        <section id="contact" class="contact">
            <p>Contact us at: <a href="tel:+255716959578">0716 959 578</a></p>
        </section>
        <footer>
            &copy; 2024 Chino Car Parking Solution. All rights reserved.
        </footer>
    </div>
</body>
</html>
