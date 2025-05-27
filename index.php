<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Chino Car Parking Solution</title>
    <link rel="stylesheet" href="custom.css" />
    <style>
        .hero {
            text-align: center;
            padding: 4rem 2rem;
        }
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.6);
        }
        .hero p {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            color: #d1d5db;
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
        .contact {
            background: white;
            color: #2563eb;
            padding: 2rem;
            border-radius: 0.5rem;
            max-width: 400px;
            margin: 0 auto;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            font-size: 1.25rem;
            font-weight: 600;
            text-align: center;
        }
        .features, .testimonials, .hours-pricing, .faq, .social {
            max-width: 960px;
            margin: 3rem auto;
            background: white;
            border-radius: 0.5rem;
            padding: 2rem;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
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
        .map-container {
            text-align: center;
            margin: 2rem 0;
        }
        footer {
            text-align: center;
            padding: 1rem;
            color: #d1d5db;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h2 class="navbar-brand">Chino Car Parking Solution</h2>
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
                width="100%" height="300" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </section>
        <section class="features">
            <h3>Why Choose Chino Car Parking?</h3>
            <ul>
                <li>Secure and monitored parking area</li>
                <li>Convenient location at Kimara, Golani Kijiweni</li>
                <li>Affordable pricing and flexible options</li>
                <li>24/7 customer support and assistance</li>
                <li>Easy booking and quick entry/exit process</li>
            </ul>
        </section>
        <section class="testimonials">
            <h3>What Our Customers Say</h3>
            <blockquote>"Safe and reliable parking service. Highly recommend!" - John M.</blockquote>
            <blockquote>"Convenient location and friendly staff. Will use again." - Asha K.</blockquote>
            <blockquote>"Affordable and secure. Great experience overall." - Michael T.</blockquote>
        </section>
        <section class="hours-pricing">
            <h3>Operating Hours & Pricing</h3>
            <p><strong>Operating Hours:</strong> 6:00 AM - 10:00 PM (Everyday)</p>
            <p><strong>Pricing:</strong> TZS 1,000 per hour, discounts for monthly subscriptions</p>
        </section>
        <section class="faq">
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
                <a href="#" style="color:#2563eb; margin-right: 1rem;">Facebook</a>
                <a href="#" style="color:#2563eb; margin-right: 1rem;">Twitter</a>
                <a href="#" style="color:#2563eb;">Instagram</a>
            </p>
        </section>
        <section class="contact">
            <p>Contact us at: <a href="tel:+255716959578">0716 959 578</a></p>
        </section>
        <footer>
            &copy; 2024 Chino Car Parking Solution. All rights reserved.
        </footer>
    </div>
</body>
</html>
