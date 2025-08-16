<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Plans.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';

// Globals
global $link;

// Instantiate permissions class
$perm = new Permission();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="robots" content="noindex" />
  <meta name="keywords" content="features, media hosting, cloud, decentralized, primal, nostr, damus image uploader, image link, snort.social, astril.ninja, image, uploader, media upload, damus pictures, video uploader,nostr repository " />
  <meta name="description" content="A complete and detailed list of nostr.build features with images supporting the nostr social media platform. nostr.build is a cloud media hosting service that is paid in bitcoin and lightning and hosts user images to share." />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="stylesheet" href="/styles/twbuild.css?v=2df4bba100936abefa8c1ef11e27421f" />
  <link rel="stylesheet" href="/styles/index.css?v=16013407201d48c976a65d9ea88a77a3" />
  <link rel="stylesheet" href="/styles/header.css?v=19cde718a50bd676387bbe7e9e24c639" />
  <link rel="icon" href="https://cdn.nostr.build/assets/01.png" />
  <script defer src="/scripts/fw/alpinejs.min.js?v=34fbe266eb872c1a396b8bf9022b7105"></script>

  <style>
    [x-cloak] {
      display: none !important;
    }

    /* Performance optimizations */
    * {
      box-sizing: border-box;
    }

    /* Force hardware acceleration for smooth animations */
    .scroll-reveal,
    .feature-card,
    .floating-animation {
      will-change: transform;
      transform: translateZ(0);
      backface-visibility: hidden;
      perspective: 1000px;
    }

    /* Fast, responsive scroll reveal animations */
    .scroll-reveal {
      opacity: 0;
      transform: translate3d(0, 30px, 0);
      transition: opacity 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94),
        transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    .scroll-reveal.revealed {
      opacity: 1;
      transform: translate3d(0, 0, 0);
    }

    /* Slightly more subtle animation for feature cards */
    .feature-card.scroll-reveal {
      transform: translate3d(0, 20px, 0) scale(0.98);
      transition: opacity 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94),
        transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    .feature-card.scroll-reveal.revealed {
      opacity: 1;
      transform: translate3d(0, 0, 0) scale(1);
    }

    /* Optimized feature cards */
    .feature-card {
      backdrop-filter: blur(20px);
      background: rgba(41, 37, 86, 0.6);
      border: 1px solid rgba(255, 255, 255, 0.1);
      transition: transform 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94),
        background-color 0.3s ease,
        border-color 0.3s ease,
        box-shadow 0.3s ease;
      transform: translate3d(0, 0, 0);
    }

    .feature-card:hover {
      transform: translate3d(0, -8px, 0);
      background: rgba(41, 37, 86, 0.8);
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 25px 50px rgba(87, 69, 141, 0.3);
    }

    /* Optimized gradient text */
    .gradient-text {
      background: linear-gradient(184.15deg, #ffffff 47.52%, #884ea4 96.61%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      transform: translateZ(0);
    }

    /* Optimized floating animation */
    .floating-animation {
      animation: float 6s ease-in-out infinite;
      animation-fill-mode: both;
    }

    @keyframes float {

      0%,
      100% {
        transform: translate3d(0, 0, 0);
      }

      50% {
        transform: translate3d(0, -20px, 0);
      }
    }

    /* Reduced motion for accessibility */
    @media (prefers-reduced-motion: reduce) {
      .floating-animation {
        animation: none;
      }

      .scroll-reveal {
        transition-duration: 0.3s;
      }
    }

    /* Smooth scrolling with better performance */
    html {
      scroll-behavior: smooth;
      scroll-padding-top: 2rem;
    }

    /* Optimized scrollbar */
    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: rgba(41, 37, 86, 0.3);
    }

    ::-webkit-scrollbar-thumb {
      background: linear-gradient(180deg, #884ea4, #292556);
      border-radius: 4px;
    }

    /* Text performance optimizations */
    .text-shadow-custom {
      text-shadow: 0px 4px 32px rgba(159, 108, 209, 0.46);
      text-rendering: optimizeLegibility;
    }

    /* Background optimization */
    body {
      transform: translateZ(0);
    }

    /* Reduce SVG complexity on lower-end devices */
    @media (max-width: 768px) {
      .floating-animation {
        animation-duration: 8s;
      }
    }

    /* Ultra-simple static background elements - no animation flicker */
    .bg-element {
      position: absolute;
      border-radius: 50%;
      filter: blur(40px);
      opacity: 0.4;
      pointer-events: none;
      will-change: auto;
    }

    .bg-element-1 {
      top: 15%;
      left: 10%;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, #884ea4 0%, transparent 70%);
    }

    .bg-element-2 {
      top: 60%;
      right: 10%;
      width: 350px;
      height: 350px;
      background: radial-gradient(circle, #2edf95 0%, transparent 70%);
    }

    .bg-element-3 {
      bottom: 20%;
      left: 20%;
      width: 300px;
      height: 300px;
      background: radial-gradient(circle, #d251d5 0%, transparent 70%);
    }

    /* Beautiful JS-powered Carousel with Fade Transition */
    .carousel-track {
      transition: none;
      /* No transform transition for fade effect */
      will-change: opacity;
    }

    .carousel-slide {
      opacity: 0;
      transition: opacity 1.5s cubic-bezier(0.4, 0.0, 0.2, 1);
      position: absolute;
      top: 0;
      left: 0;
    }

    .carousel-slide.active {
      opacity: 1;
    }

    .carousel-track.no-transition .carousel-slide {
      transition: none;
    }

    /* Smooth fade overlays for polished look */
    .carousel-fade-overlay {
      opacity: 0.8;
      transition: opacity 300ms ease;
    }

    .carousel-container:hover .carousel-fade-overlay {
      opacity: 0.3;
    }

    /* Pause animation on reduced motion */
    @media (prefers-reduced-motion: reduce) {
      .carousel-slide {
        transition: none !important;
      }
    }

    /* Mobile menu fixes for features page */
    /* Fix the mobile menu z-index and background issues */
    .menu {
      z-index: 30 !important;
      background: rgba(41, 36, 84, 0.95) !important;
      backdrop-filter: blur(20px) !important;
      border: 1px solid rgba(61, 55, 131, 0.8) !important;
    }

    /* Ensure hamburger button is above everything */
    .menu_button {
      z-index: 35 !important;
      position: relative;
    }

    /* Make sure menu links are clickable */
    .menu .nav_button {
      pointer-events: auto !important;
      position: relative;
      z-index: 31 !important;
    }

    /* Ensure the header itself has proper z-index */
    .navigation_header {
      z-index: 25 !important;
      position: relative;
    }
  </style>

  <title>nostr.build - Features</title>
</head>

<body class="min-h-screen bg-gradient-to-tr from-[#292556] to-[#120a24] overflow-x-hidden">
  <header class="header relative z-20">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/mainnav.php'; ?>
  </header>

  <!-- Ultra-simple static background -->
  <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
    <div class="absolute inset-0 bg-gradient-to-tr from-[#292556] to-[#120a24]">
      <!-- Static blurred elements for subtle depth -->
      <div class="bg-element bg-element-1"></div>
      <div class="bg-element bg-element-2"></div>
      <div class="bg-element bg-element-3"></div>
    </div>
  </div>

  <main class="relative z-10 flex flex-col items-center justify-center w-full">
    <!-- Hero Section -->
    <section class="min-h-screen flex items-center justify-center relative px-4 w-full">
      <div class="text-center max-w-6xl mx-auto scroll-reveal">
        <h1 class="text-6xl md:text-8xl font-bold gradient-text text-shadow-custom mb-8 leading-tight">
          Powerful Features
        </h1>
        <p class="text-xl md:text-2xl text-[#a58ead] mb-12 max-w-3xl mx-auto leading-relaxed">
          Discover the complete suite of tools designed to elevate your Nostr experience
        </p>
        <div class="floating-animation">
          <a href="#what-you-get" class="inline-block p-6 rounded-3xl bg-gradient-to-r from-[#884ea4] to-[#292556] shadow-lg hover:shadow-xl transition-all duration-300 cursor-pointer">
            <!-- nostr.build logo -->
            <svg class="w-24 h-24 mx-auto" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 33.4 39">
              <defs>
                <linearGradient id="heroLogo" x1="8.25" y1="-326.6" x2="8.25" y2="-297.6" gradientTransform="translate(0 336.6)" gradientUnits="userSpaceOnUse">
                  <stop offset="0" stop-color="#9b4399"></stop>
                  <stop offset="1" stop-color="#3d1e56"></stop>
                  <stop offset="1" stop-color="#231815"></stop>
                </linearGradient>
              </defs>
              <path d="M16.7 0 .1 9.8l16.6 9.7 16.5-9.7L16.7 0Z" fill="#70429a" stroke-width="0"></path>
              <path fill="#fff" d="m16.7 1.7-4.6 2.6v10.8l4.6 2.7 4.6-2.7V9.7l4.5-2.6-9.1-5.4Zm-3 6.3c-1.23-.11-1.13-1.98.1-2 1.3 0 1.27 2.09 0 2m6.8 1.3L16.8 7l3.8-2.3L24.3 7l-3.7 2.3Z"></path>
              <path d="M0 10v19.3L16.5 39V19.7L0 10Z" fill="url(#heroLogo)" stroke-width="0"></path>
              <path fill="#fff" d="m14.4 35.2-3-1.8v-.9L5.1 21.8l-.8-.4v8L2 28.1V13.8l3 1.7v1l6.2 10.7.8.4v-8l2.3 1.3.1 14.3Z"></path>
              <path d="M16.8 19.7V39l16.6-9.7V10l-16.6 9.7Z" stroke-width="0" fill="#3d1e56"></path>
              <path fill="#fff" d="M31.3 16.4 29 15.1l-10 5.8v14.3L29.7 29l1.6-2.7v-3.6l-1.6-.9 1.6-2.7v-2.7Zm-2.5 9.9-1.1 2-6.5 3.8v-4.5l6.5-3.8 1.2.7-.1 1.8Zm0-6.3-1 2-6.5 3.8v-4.4l6.5-3.8 1.2.6-.2 1.8Z"></path>
            </svg>
          </a>
        </div>
      </div>
    </section>

    <!-- What You Get Section -->
    <section id="what-you-get" class="py-20 px-4 w-full">
      <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16 scroll-reveal">
          <h2 class="text-4xl md:text-6xl font-bold gradient-text mb-6">What You Get</h2>
          <p class="text-xl text-[#a58ead] max-w-3xl mx-auto">
            Every plan is designed to unlock specific capabilities for your unique Nostr journey
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
          <!-- Purist Benefits -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal border-2 border-[#2edf95]/30">
            <div class="mb-6">
              <h3 class="text-2xl font-bold text-white mb-4">Purist Plan Benefits</h3>
              <p class="text-[#a58ead] mb-6">Essential features for privacy-conscious users</p>
            </div>

            <div class="space-y-4">
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#2edf95] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium"><?= SiteConfig::STORAGE_LIMITS[Plans::PURIST]['message'] ?> Private Storage</p>
                  <p class="text-[#d0bed8] text-sm">Organize your content privately</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#2edf95] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium"><?= SiteConfig::PURIST_PER_FILE_UPLOAD_LIMIT / 1024 / 1024 ?>MB File Uploads</p>
                  <p class="text-[#d0bed8] text-sm">Share high-quality videos and images</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#2edf95] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">Clean Interface</p>
                  <p class="text-[#d0bed8] text-sm">No complexity, just what you need</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#2edf95] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">Global CDN Delivery</p>
                  <p class="text-[#d0bed8] text-sm">Fast content delivery worldwide</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Professional Benefits -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal border-2 border-[#884ea4]/30">
            <div class="mb-6">
              <h3 class="text-2xl font-bold text-white mb-4">Professional Plan Benefits</h3>
              <p class="text-[#a58ead] mb-6">Everything in Purist, plus creator-focused tools</p>
            </div>

            <div class="space-y-4">
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#884ea4] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium"><?= SiteConfig::STORAGE_LIMITS[Plans::PROFESSIONAL]['message'] ?> Private Storage</p>
                  <p class="text-[#d0bed8] text-sm">5x more space for your content</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#884ea4] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">PDF & SVG Support</p>
                  <p class="text-[#d0bed8] text-sm">Virus-scanned documents and vector graphics</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#884ea4] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">AI Studio Access</p>
                  <p class="text-[#d0bed8] text-sm">Create images with SDXL-Lightning</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#884ea4] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">Advanced Analytics</p>
                  <p class="text-[#d0bed8] text-sm">Track engagement and performance</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Creator Benefits -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal border-2 border-[#f78533]/30">
            <div class="mb-6">
              <h3 class="text-2xl font-bold text-white mb-4">Creator Plan Benefits</h3>
              <p class="text-[#a58ead] mb-6">Everything in Professional, plus creator tools</p>
            </div>

            <div class="space-y-4">
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#f78533] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium"><?= SiteConfig::STORAGE_LIMITS[Plans::CREATOR]['message'] ?> Private Storage</p>
                  <p class="text-[#d0bed8] text-sm">Massive storage for content creators</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#f78533] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">Premium AI Credits</p>
                  <p class="text-[#d0bed8] text-sm">More AI generation capacity</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#f78533] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">Priority Support</p>
                  <p class="text-[#d0bed8] text-sm">Faster response times</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#f78533] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">Enhanced Analytics</p>
                  <p class="text-[#d0bed8] text-sm">Detailed creator insights</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Advanced Benefits -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal border-2 border-[#dabd55]/30">
            <div class="mb-6">
              <h3 class="text-2xl font-bold text-white mb-4">Advanced Plan Benefits</h3>
              <p class="text-[#a58ead] mb-6">Everything in Creator, plus enterprise-grade infrastructure</p>
            </div>

            <div class="space-y-4">
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#dabd55] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium"><?= SiteConfig::STORAGE_LIMITS[Plans::ADVANCED]['message'] ?> Private Storage</p>
                  <p class="text-[#d0bed8] text-sm">Enterprise-level storage capacity</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#dabd55] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">Maximum AI Credits</p>
                  <p class="text-[#d0bed8] text-sm">Unlimited creative possibilities</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#dabd55] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">Custom Solutions</p>
                  <p class="text-[#d0bed8] text-sm">Tailored features for your needs</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#dabd55] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">White-Glove Support</p>
                  <p class="text-[#d0bed8] text-sm">Dedicated account management</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Privacy & Sovereignty Section -->
    <section class="py-20 px-4 w-full">
      <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16 scroll-reveal">
          <h2 class="text-4xl md:text-6xl font-bold gradient-text mb-6">Your Content, Your Control</h2>
          <p class="text-xl text-[#a58ead] max-w-3xl mx-auto">
            Built on principles that put your privacy and digital sovereignty first
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
          <!-- No Ads -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal text-center">
            <div class="w-16 h-16 bg-gradient-to-r from-[#2edf95] to-[#07847c] rounded-2xl flex items-center justify-center mx-auto mb-6">
              <div class="text-3xl">🚫</div>
            </div>
            <h3 class="text-xl font-bold text-white mb-4">Never Any Ads</h3>
            <p class="text-[#d0bed8] leading-relaxed text-sm">
              Your content stays clean and distraction-free. We're funded by our users, not advertisers, so your privacy isn't the product.
            </p>
          </div>

          <!-- Privacy First -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal text-center">
            <div class="w-16 h-16 bg-gradient-to-r from-[#884ea4] to-[#292556] rounded-2xl flex items-center justify-center mx-auto mb-6">
              <div class="text-3xl">🔒</div>
            </div>
            <h3 class="text-xl font-bold text-white mb-4">Privacy by Design</h3>
            <p class="text-[#d0bed8] leading-relaxed text-sm">
              Your private storage is truly private. No tracking, no profiling, no data mining. Your content belongs to you.
            </p>
          </div>

          <!-- Full Sovereignty -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal text-center">
            <div class="w-16 h-16 bg-gradient-to-r from-[#d251d5] to-[#8a0578] rounded-2xl flex items-center justify-center mx-auto mb-6">
              <div class="text-3xl">👑</div>
            </div>
            <h3 class="text-xl font-bold text-white mb-4">Digital Sovereignty</h3>
            <p class="text-[#d0bed8] leading-relaxed text-sm">
              Built on Nostr protocol. You control your identity, your keys, your data. No platform can silence or censor you.
            </p>
          </div>

          <!-- True Ownership -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal text-center">
            <div class="w-16 h-16 bg-gradient-to-r from-[#dabd55] to-[#f78533] rounded-2xl flex items-center justify-center mx-auto mb-6">
              <div class="text-3xl">🔑</div>
            </div>
            <h3 class="text-xl font-bold text-white mb-4">Full Ownership</h3>
            <p class="text-[#d0bed8] leading-relaxed text-sm">
              Your content, your rules. Export anytime, migrate anywhere. No vendor lock-in, just pure digital freedom.
            </p>
          </div>
        </div>

        <!-- Enhanced Values Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 mb-16">
          <!-- Bitcoin-Only Approach -->
          <div class="feature-card rounded-3xl p-10 scroll-reveal">
            <div class="mb-6">
              <h3 class="text-3xl font-bold text-white mb-4">Bitcoin-Only Foundation</h3>
              <p class="text-lg text-[#a58ead] mb-6">
                Sound money for sound infrastructure
              </p>
            </div>
            <div class="space-y-4">
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#f78533] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">Lightning & On-Chain Payments</p>
                  <p class="text-[#d0bed8] text-sm">Fast, private payments with ultimate finality</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#f78533] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">No Fiat Complexity</p>
                  <p class="text-[#d0bed8] text-sm">No banks, no KYC, no payment processors tracking you</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#f78533] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">Aligned Incentives</p>
                  <p class="text-[#d0bed8] text-sm">Both you and we benefit from Bitcoin's success</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Long-term Protection -->
          <div class="feature-card rounded-3xl p-10 scroll-reveal">
            <div class="mb-6">
              <h3 class="text-3xl font-bold text-white mb-4">Built to Last</h3>
              <p class="text-lg text-[#a58ead] mb-6">
                Your content survives beyond your subscription
              </p>
            </div>
            <div class="space-y-4">
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#2edf95] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">1-Year Grace Period</p>
                  <p class="text-[#d0bed8] text-sm">Media stays online for a full year after account expiration</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#2edf95] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">Open Protocol Foundation</p>
                  <p class="text-[#d0bed8] text-sm">Built on Nostr and Blossom - protocols that can't be shut down</p>
                </div>
              </div>
              <div class="flex items-start gap-3">
                <div class="w-6 h-6 bg-[#2edf95] rounded-full flex-shrink-0 mt-1"></div>
                <div>
                  <p class="text-white font-medium">Multiple Backup Systems</p>
                  <p class="text-[#d0bed8] text-sm">Your content is protected across multiple providers and regions</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Early Exit CTA -->
        <div class="text-center scroll-reveal">
          <div class="bg-gradient-to-br from-[#292556]/60 to-[#1e1530]/60 backdrop-blur-xl rounded-3xl p-12 border border-[#392f73]">
            <h3 class="text-3xl md:text-4xl font-bold gradient-text mb-6">Ready to Take Control?</h3>
            <p class="text-xl text-[#d0bed8] mb-8 max-w-2xl mx-auto">
              Join the movement toward digital sovereignty. Your content, your rules, your future.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
              <a href="/plans/" class="inline-block px-8 py-4 bg-gradient-to-r from-[#884ea4] to-[#292556] text-white font-semibold rounded-xl hover:shadow-lg transform hover:-translate-y-1 transition-all duration-300 text-lg">
                Choose Your Plan
              </a>
              <span class="text-[#a58ead] text-lg">or</span>
              <a href="#advanced-capabilities" class="inline-block px-8 py-4 bg-transparent border-2 border-[#884ea4] text-[#884ea4] font-semibold rounded-xl hover:bg-[#884ea4] hover:text-white transition-all duration-300 text-lg">
                Learn More Features
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>


    <!-- Advanced Features Showcase -->
    <section id="advanced-capabilities" class="py-20 px-4">
      <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16 scroll-reveal">
          <h2 class="text-4xl md:text-6xl font-bold gradient-text mb-6">Capabilities</h2>
          <p class="text-xl text-[#a58ead] max-w-3xl mx-auto">
            Powerful tools designed for creators, developers, and power users
          </p>
        </div>

        <!-- Media Management Portal Feature -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center mb-20">
          <div class="scroll-reveal">
            <h3 class="text-4xl font-bold gradient-text mb-6">Media Management Portal</h3>
            <p class="text-xl text-[#d0bed8] mb-8 leading-relaxed">
              Professional media organization in your own private portal. Drag & drop files, create folders, bulk operations, and complete control over your content library. See the full interface in action.
            </p>
            <div class="space-y-4">
              <div class="flex items-center">
                <span class="text-[#884ea4] mr-3">✓</span>
                <span class="text-[#d0bed8]">Drag & drop interface</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#884ea4] mr-3">✓</span>
                <span class="text-[#d0bed8]">Folder organization</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#884ea4] mr-3">✓</span>
                <span class="text-[#d0bed8]">Bulk operations</span>
              </div>
            </div>
          </div>
          <div class="scroll-reveal">
            <div class="bg-gradient-to-br from-[#292556] to-[#1e1530] rounded-2xl border border-[#392f73] overflow-hidden">
              <video class="w-full h-auto"
                autoplay loop muted playsinline>
                <source src="https://v.nostr.build/o7Kcp0r1c0LgRR9G.mp4" type="video/mp4">
                Your browser does not support the video tag.
              </video>
            </div>
          </div>
        </div>

        <!-- AI Studio Feature -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center mb-20">
          <div class="scroll-reveal lg:order-2">
            <h3 class="text-4xl font-bold gradient-text mb-6">AI Studio</h3>
            <p class="text-xl text-[#d0bed8] mb-8 leading-relaxed">
              Create stunning images with multiple AI models including SDXL-Lightning, Stable Diffusion, and Flux.1.
              Unlike ChatGPT/DALL-E, Discord/Midjourney, or X/Grok2 that lock you into a single model, we give you choice and flexibility.
              <a href="https://cdn.nostr.build/assets/images/nb_aistudio_compete01.pdf" target="_blank" class="text-[#884ea4] hover:text-[#a58ead] transition-colors duration-200 underline">
                See our competitive comparison here.
              </a>
            </p>
            <div class="space-y-4">
              <div class="flex items-center">
                <span class="text-[#884ea4] mr-3">✓</span>
                <span class="text-[#d0bed8]">Multiple AI models available</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#884ea4] mr-3">✓</span>
                <span class="text-[#d0bed8]">Integrated media management</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#884ea4] mr-3">✓</span>
                <span class="text-[#d0bed8]">Credits system for fair usage</span>
              </div>
            </div>
          </div>
          <div class="scroll-reveal">
            <!-- Beautiful AI Studio Carousel -->
            <div class="bg-gradient-to-br from-[#292556] to-[#1e1530] rounded-2xl h-96 border border-[#392f73] overflow-hidden relative carousel-container">
              <div class="carousel-track relative w-full h-full" id="aiStudioCarousel">
                <!-- AI Studio images -->
                <div class="carousel-slide w-full h-full active">
                  <img src="https://cdn.nostr.build/assets/fpv2/ai1.png" alt="AI Studio Interface" class="w-full h-full object-cover">
                </div>
                <div class="carousel-slide w-full h-full">
                  <img src="https://cdn.nostr.build/assets/fpv2/ai2.png" alt="AI Model Selection" class="w-full h-full object-cover">
                </div>
                <div class="carousel-slide w-full h-full">
                  <img src="https://cdn.nostr.build/assets/fpv2/ai3.png" alt="AI Generated Results" class="w-full h-full object-cover">
                </div>
              </div>
              <!-- Subtle fade overlays for polish -->
              <div class="absolute inset-y-0 left-0 w-8 bg-gradient-to-r from-[#292556] to-transparent pointer-events-none carousel-fade-overlay"></div>
              <div class="absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-[#1e1530] to-transparent pointer-events-none carousel-fade-overlay"></div>
            </div>
          </div>
        </div>

        <!-- Media Analytics Feature -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center mb-20">
          <div class="scroll-reveal">
            <h3 class="text-4xl font-bold gradient-text mb-6">Advanced Analytics & Insights</h3>
            <p class="text-xl text-[#d0bed8] mb-8 leading-relaxed">
              Understand your audience like never before. Track engagement patterns, content performance, and audience demographics to create content that truly resonates. Make data-driven decisions to grow your reach and maximize impact.
            </p>
            <div class="space-y-4">
              <div class="flex items-center">
                <span class="text-[#2edf95] mr-3">✓</span>
                <span class="text-[#d0bed8]">Audience engagement tracking</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#2edf95] mr-3">✓</span>
                <span class="text-[#d0bed8]">Content performance insights</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#2edf95] mr-3">✓</span>
                <span class="text-[#d0bed8]">Geographic and temporal analytics</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#2edf95] mr-3">✓</span>
                <span class="text-[#d0bed8]">Content optimization recommendations</span>
              </div>
            </div>
          </div>
          <div class="scroll-reveal">
            <!-- Beautiful JS-powered Analytics Carousel -->
            <div class="bg-gradient-to-br from-[#292556] to-[#1e1530] rounded-2xl h-96 border border-[#392f73] overflow-hidden relative carousel-container">
              <div class="carousel-track relative w-full h-full" id="analyticsCarousel">
                <!-- Analytics images -->
                <div class="carousel-slide w-full h-full active">
                  <img src="https://cdn.nostr.build/assets/fpv2/an-byref.png" alt="Analytics by Reference" class="w-full h-full object-cover">
                </div>
                <div class="carousel-slide w-full h-full">
                  <img src="https://cdn.nostr.build/assets/fpv2/an-dayofweek.png" alt="Analytics by Day of Week" class="w-full h-full object-cover">
                </div>
                <div class="carousel-slide w-full h-full">
                  <img src="https://cdn.nostr.build/assets/fpv2/an-hourofday.png" alt="Analytics by Hour of Day" class="w-full h-full object-cover">
                </div>
                <div class="carousel-slide w-full h-full">
                  <img src="https://cdn.nostr.build/assets/fpv2/an-map.png" alt="Analytics Map View" class="w-full h-full object-cover">
                </div>
                <div class="carousel-slide w-full h-full">
                  <img src="https://cdn.nostr.build/assets/fpv2/an-ts-country.png" alt="Analytics by Country Timeline" class="w-full h-full object-cover">
                </div>
                <div class="carousel-slide w-full h-full">
                  <img src="https://cdn.nostr.build/assets/fpv2/an-ts.png" alt="Analytics Timeline" class="w-full h-full object-cover">
                </div>
                <div class="carousel-slide w-full h-full">
                  <img src="https://cdn.nostr.build/assets/fpv2/an-wd.png" alt="Analytics Weekly Data" class="w-full h-full object-cover">
                </div>
              </div>
              <!-- Subtle fade overlays for polish -->
              <div class="absolute inset-y-0 left-0 w-8 bg-gradient-to-r from-[#292556] to-transparent pointer-events-none carousel-fade-overlay"></div>
              <div class="absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-[#1e1530] to-transparent pointer-events-none carousel-fade-overlay"></div>
            </div>
          </div>
        </div>

        <!-- Blossom Protocol Support -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center mb-20">
          <div class="scroll-reveal lg:order-2">
            <h3 class="text-4xl font-bold gradient-text mb-6">Blossom Protocol Integration</h3>
            <p class="text-xl text-[#d0bed8] mb-8 leading-relaxed">
              Native support for the cutting-edge Blossom protocol - the future of decentralized media storage.
              Each user gets their own dedicated server domain with cryptographic authentication and seamless integration across the Nostr ecosystem.
            </p>
            <div class="space-y-4">
              <div class="flex items-center">
                <span class="text-[#884ea4] mr-3">✓</span>
                <span class="text-[#d0bed8]">Personal dedicated server domain</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#884ea4] mr-3">✓</span>
                <span class="text-[#d0bed8]">Cryptographic Nostr event authentication</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#884ea4] mr-3">✓</span>
                <span class="text-[#d0bed8]">Full API compatibility with Blossom clients</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#884ea4] mr-3">✓</span>
                <span class="text-[#d0bed8]">Managed from your regular account dashboard</span>
              </div>
            </div>
          </div>
          <div class="scroll-reveal lg:order-1">
            <!-- Placeholder for Blossom protocol visualization -->
            <div class="bg-gradient-to-br from-[#292556] to-[#1e1530] rounded-2xl h-80 border border-[#392f73] overflow-hidden">
              <img src="https://cdn.nostr.build/assets/images/blossom01.png" alt="Blossom Protocol Visualization" class="w-full h-full object-cover">
            </div>
          </div>
        </div>

        <!-- Native Client Integration -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center mb-20">
          <div class="scroll-reveal">
            <h3 class="text-4xl font-bold gradient-text mb-6">Native Client Integration</h3>
            <p class="text-xl text-[#d0bed8] mb-8 leading-relaxed">
              Seamlessly integrated into the most popular Nostr clients. No setup required - just start sharing your content directly from your favorite apps. Your media works everywhere in the Nostr ecosystem.
            </p>
            <div class="space-y-4">
              <div class="flex items-center">
                <span class="text-[#2edf95] mr-3">✓</span>
                <span class="text-[#d0bed8]"><strong>Primal</strong> - Universal platform (iOS, Android, Web) via Blossom</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#2edf95] mr-3">✓</span>
                <span class="text-[#d0bed8]"><strong>YakiHonne, Damus, Amethyst</strong> - Major mobile clients</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#2edf95] mr-3">✓</span>
                <span class="text-[#d0bed8]"><strong>Snort, Coracle, noStrudel</strong> - Leading web clients</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#2edf95] mr-3">✓</span>
                <span class="text-[#d0bed8]">Hassle-free - works out of the box</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#2edf95] mr-3">✓</span>
                <span class="text-[#d0bed8]">Universal compatibility across the ecosystem</span>
              </div>
            </div>
          </div>
          <div class="scroll-reveal">
            <!-- Native clients showcase -->
            <div class="bg-gradient-to-br from-[#292556] to-[#1e1530] rounded-2xl h-80 border border-[#392f73] overflow-hidden flex items-center justify-center">
              <img src="https://cdn.nostr.build/assets/fpv2/clogos.png" alt="Global CDN Map" class="w-full h-full object-cover">
            </div>
          </div>
        </div>

        <!-- Share Direct to Nostr Feature -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center mb-20">
          <div class="scroll-reveal lg:order-2">
            <h3 class="text-4xl font-bold gradient-text mb-6">Share Direct to Nostr</h3>
            <p class="text-xl text-[#d0bed8] mb-8 leading-relaxed">
              Upload or create media and share it directly to Nostr with or without a note - no other client needed!
              This tighter integration takes nostr.build steps closer to becoming a true Nostr client.
            </p>
            <div class="space-y-4">
              <div class="flex items-center">
                <span class="text-[#2edf95] mr-3">✓</span>
                <span class="text-[#d0bed8]">Direct publishing to Nostr</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#2edf95] mr-3">✓</span>
                <span class="text-[#d0bed8]">No external client required</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#2edf95] mr-3">✓</span>
                <span class="text-[#d0bed8]">Integrated workflow</span>
              </div>
            </div>
          </div>
          <div class="scroll-reveal lg:order-1">
            <div class="bg-gradient-to-br from-[#292556] to-[#1e1530] rounded-2xl h-96 border border-[#392f73] overflow-hidden">
              <video class="w-full h-full object-cover rounded-lg"
                autoplay loop muted playsinline>
                <source src="https://cdn.nostr.build/assets/images/share_to_nostr01.mp4" type="video/mp4">
                Your browser does not support the video tag.
              </video>
            </div>
          </div>
        </div>

        <!-- Creator Showcase Feature -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center mb-20">
          <div class="scroll-reveal">
            <h3 class="text-4xl font-bold gradient-text mb-6">Creator Showcase Pages</h3>
            <div class="inline-block px-3 py-1 bg-gradient-to-r from-[#d251d5] to-[#f78533] text-white text-sm font-semibold rounded-full mb-4">
              Creator & Advanced Plans
            </div>
            <p class="text-xl text-[#d0bed8] mb-8 leading-relaxed">
              Host your own professional creator page on the nostr.build domain. Showcase your portfolio, build your brand, and make it easy for fans to discover, follow, and support your work.
            </p>
            <div class="space-y-4">
              <div class="flex items-center">
                <span class="text-[#d251d5] mr-3">✓</span>
                <span class="text-[#d0bed8]">Custom creator.nostr.build page</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#d251d5] mr-3">✓</span>
                <span class="text-[#d0bed8]">Portfolio showcase</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#d251d5] mr-3">✓</span>
                <span class="text-[#d0bed8]">Fan discovery and engagement</span>
              </div>
            </div>
          </div>
          <div class="scroll-reveal">
            <div class="bg-gradient-to-br from-[#292556] to-[#1e1530] rounded-2xl h-96 border border-[#392f73] overflow-hidden">
              <video class="w-full h-full object-cover rounded-lg"
                autoplay loop muted playsinline>
                <source src="https://cdn.nostr.build/assets/images/creators11.mp4" type="video/mp4">
                Your browser does not support the video tag.
              </video>
            </div>
          </div>
        </div>

        <!-- Global CDN Feature -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
          <div class="scroll-reveal lg:order-2">
            <h3 class="text-4xl font-bold gradient-text mb-6">Lightning-Fast Global CDN</h3>
            <p class="text-xl text-[#d0bed8] mb-8 leading-relaxed">
              Your content delivered at the speed of light with our worldwide content delivery network.
              Backed up across multiple providers for maximum reliability.
            </p>
            <div class="space-y-4">
              <div class="flex items-center">
                <span class="text-[#dabd55] mr-3">✓</span>
                <span class="text-[#d0bed8]">Global edge locations</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#dabd55] mr-3">✓</span>
                <span class="text-[#d0bed8]">Multiple provider backup</span>
              </div>
              <div class="flex items-center">
                <span class="text-[#dabd55] mr-3">✓</span>
                <span class="text-[#d0bed8]">Optimized compression</span>
              </div>
            </div>
          </div>
          <div class="scroll-reveal lg:order-1">
            <!-- Placeholder for CDN diagram -->
            <div class="bg-gradient-to-br from-[#292556] to-[#1e1530] rounded-2xl h-80 border border-[#392f73] overflow-hidden">
              <!-- 634x317 image of the globe, fill to cover the div https://cdn.nostr.build/assets/fpv2/ww-connect@0.33x.png -->
              <img src="https://cdn.nostr.build/assets/fpv2/ww-connect@0.33x.png" alt="Global CDN Map" class="w-full h-full object-cover">
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Account Tiers Section -->
    <section id="built-for-every-creator" class="py-20 px-4 relative w-full">
      <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16 scroll-reveal">
          <h2 class="text-4xl md:text-6xl font-bold gradient-text pb-5 mb-6">Built for Every Creator</h2>
          <p class="text-xl text-[#a58ead] max-w-3xl mx-auto">
            From privacy enthusiasts to professional content creators, we have the perfect tier for your needs
          </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 mb-20">

          <!-- Purist Tier -->
          <div class="feature-card rounded-3xl p-5 sm:p-10 scroll-reveal relative">
            <div class="mb-8">
              <h3 class="text-3xl font-bold text-white mb-4">Purist</h3>
              <p class="text-lg text-[#a58ead] mb-6">
                <strong>Perfect for:</strong> Privacy-conscious users, minimalists, and those new to Nostr
              </p>
            </div>

            <div class="space-y-6">
              <div class="bg-gradient-to-r from-[#2edf95]/10 to-[#07847c]/10 rounded-xl p-6 border border-[#2edf95]/20">
                <h4 class="text-xl font-semibold text-white mb-3">🧘‍♀️ Clean & Simple Experience</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  <strong><?= SiteConfig::STORAGE_LIMITS[Plans::PURIST]['message'] ?></strong> with support for essential image and video formats. No complexity, just pure functionality for users who value simplicity and want to keep their content organized without overwhelming features.
                </p>
              </div>

              <div class="bg-gradient-to-r from-[#2edf95]/10 to-[#07847c]/10 rounded-xl p-6 border border-[#2edf95]/20">
                <h4 class="text-xl font-semibold text-white mb-3">📁 Larger File Support</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  Upload files up to <strong><?= SiteConfig::PURIST_PER_FILE_UPLOAD_LIMIT / 1024 / 1024 ?>MB each</strong> - perfect for high-quality photos, longer videos, or presentations. No more compression headaches or splitting files.
                </p>
              </div>

              <div class="bg-gradient-to-r from-[#2edf95]/10 to-[#07847c]/10 rounded-xl p-6 border border-[#2edf95]/20">
                <h4 class="text-xl font-semibold text-white mb-3">🎯 Focus on Content</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  All the core features you need: <strong>media management, direct Nostr sharing, global CDN delivery, and detailed statistics</strong> - without the complexity of advanced tools.
                </p>
              </div>

              <div class="mt-8 p-4 bg-[#2edf95]/5 rounded-lg border-l-4 border-[#2edf95]">
                <p class="text-sm text-[#2edf95] font-semibold">
                  💭 "I just want reliable hosting for my photos and memes without paying for features I don't use"
                </p>
              </div>
            </div>
          </div>

          <!-- Professional Tier -->
          <div class="feature-card rounded-3xl p-5 sm:p-10 scroll-reveal relative border-2 border-[#884ea4]/30">
            <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
              <span class="bg-gradient-to-r from-[#884ea4] to-[#292556] text-white px-3 py-1.5 sm:px-6 sm:py-2 rounded-full text-xs sm:text-sm font-semibold">
                POPULAR
              </span>
            </div>
            <div class="mb-8">
              <h3 class="text-3xl font-bold text-white mb-4">Professional</h3>
              <p class="text-lg text-[#a58ead] mb-6">
                <strong>Perfect for:</strong> Content creators, influencers, and active Nostr users
              </p>
            </div>

            <div class="space-y-6">
              <div class="bg-gradient-to-r from-[#884ea4]/10 to-[#292556]/10 rounded-xl p-6 border border-[#884ea4]/20">
                <h4 class="text-xl font-semibold text-white mb-3">💼 Professional Storage</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  <strong><?= SiteConfig::STORAGE_LIMITS[Plans::PROFESSIONAL]['message'] ?></strong> with support for <strong>virus-scanned PDF and SVG files</strong>. Perfect for sharing presentations, design files, infographics, and professional documents directly through Nostr.
                </p>
              </div>

              <div class="bg-gradient-to-r from-[#884ea4]/10 to-[#292556]/10 rounded-xl p-6 border border-[#884ea4]/20">
                <h4 class="text-xl font-semibold text-white mb-3">🎨 AI-Powered Creation</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  Access to <strong>AI Studio with SDXL-Lightning and Stable Diffusion models</strong>. Create stunning images for your content, social media posts, or marketing materials. Includes bonus credits to get you started.
                </p>
              </div>

              <div class="bg-gradient-to-r from-[#884ea4]/10 to-[#292556]/10 rounded-xl p-6 border border-[#884ea4]/20">
                <h4 class="text-xl font-semibold text-white mb-3">🗂️ Media Management Portal</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  <strong>Professional media organization</strong> with drag & drop, folders, renaming, and bulk operations - everything you need to manage your content like a pro.
                </p>
              </div>

              <div class="bg-gradient-to-r from-[#884ea4]/10 to-[#292556]/10 rounded-xl p-6 border border-[#884ea4]/20">
                <h4 class="text-xl font-semibold text-white mb-3">📊 Advanced Analytics</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  <strong>Detailed usage statistics and analytics</strong> help you understand your audience engagement. Track views, downloads, and performance metrics to optimize your content strategy.
                </p>
              </div>

              <div class="mt-8 p-4 bg-[#884ea4]/5 rounded-lg border-l-4 border-[#884ea4]">
                <p class="text-sm text-[#884ea4] font-semibold">
                  💭 "I need reliable hosting for my business content plus AI tools to create engaging visuals"
                </p>
              </div>
            </div>
          </div>

          <!-- Creator Tier -->
          <div class="feature-card rounded-3xl p-5 sm:p-10 scroll-reveal relative">
            <div class="mb-8">
              <h3 class="text-3xl font-bold text-white mb-4">Creator</h3>
              <p class="text-lg text-[#a58ead] mb-6">
                <strong>Perfect for:</strong> Full-time creators, podcasters, and content professionals
              </p>
            </div>

            <div class="space-y-6">
              <div class="bg-gradient-to-r from-[#d251d5]/10 to-[#8a0578]/10 rounded-xl p-6 border border-[#d251d5]/20">
                <h4 class="text-xl font-semibold text-white mb-3">🎬 Creator-Grade Storage</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  <strong><?= SiteConfig::STORAGE_LIMITS[Plans::CREATOR]['message'] ?></strong> with support for <strong>ZIP, PDF, and SVG files</strong>. ZIP files require extra processing time due to comprehensive virus scanning. Perfect for large video projects, podcast archives, design portfolios, and comprehensive media libraries.
                </p>
              </div>

              <div class="bg-gradient-to-r from-[#d251d5]/10 to-[#8a0578]/10 rounded-xl p-6 border border-[#d251d5]/20">
                <h4 class="text-xl font-semibold text-white mb-3">🤖 Advanced AI Studio</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  Unlimited access to <strong>Flux.1 model</strong> plus all Professional AI models. Create professional-grade images, thumbnails, and visual content that stands out. Perfect for social media, marketing, and creative projects.
                </p>
              </div>

              <div class="bg-gradient-to-r from-[#d251d5]/10 to-[#8a0578]/10 rounded-xl p-6 border border-[#d251d5]/20">
                <h4 class="text-xl font-semibold text-white mb-3">🏠 Creator Showcase</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  <strong>Host your own creators page</strong> on nostr.build domain. Showcase your best work, build your brand, and make it easy for fans to discover and support your content.
                </p>
              </div>

              <div class="bg-gradient-to-r from-[#d251d5]/10 to-[#8a0578]/10 rounded-xl p-6 border border-[#d251d5]/20">
                <h4 class="text-xl font-semibold text-white mb-3">🛡️ Redundant Backup</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  <strong>S3 backup across multiple providers</strong> ensures your creative work is never lost. Your content is automatically backed up to different data centers for maximum protection.
                </p>
              </div>

              <div class="mt-8 p-4 bg-[#d251d5]/5 rounded-lg border-l-4 border-[#d251d5]">
                <p class="text-sm text-[#d251d5] font-semibold">
                  💭 "My content is my livelihood - I need professional tools, ample storage, and bulletproof reliability"
                </p>
              </div>
            </div>
          </div>

          <!-- Advanced Tier -->
          <div class="feature-card rounded-3xl p-5 sm:p-10 scroll-reveal relative">
            <div class="mb-8">
              <h3 class="text-3xl font-bold text-white mb-4">Advanced</h3>
              <p class="text-lg text-[#a58ead] mb-6">
                <strong>Perfect for:</strong> Enterprises, organizations, and power users who need scalable solutions
              </p>
            </div>

            <div class="space-y-6">
              <div class="bg-gradient-to-r from-[#dabd55]/10 to-[#f78533]/10 rounded-xl p-6 border border-[#dabd55]/20">
                <h4 class="text-xl font-semibold text-white mb-3">🚀 Enterprise Storage</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  <strong><?= SiteConfig::STORAGE_LIMITS[Plans::ADVANCED]['message'] ?></strong> - 5x more than Creator tier. Perfect for large-scale content operations, extensive media libraries, or team collaborations.
                </p>
              </div>

              <div class="bg-gradient-to-r from-[#dabd55]/10 to-[#f78533]/10 rounded-xl p-6 border border-[#dabd55]/20">
                <h4 class="text-xl font-semibold text-white mb-3">🧪 Cutting-Edge AI</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  <strong>Extended AI Studio access</strong> with all models, experimental features, and priority access to new AI capabilities. Be the first to use breakthrough AI technology.
                </p>
              </div>

              <div class="bg-gradient-to-r from-[#dabd55]/10 to-[#f78533]/10 rounded-xl p-6 border border-[#dabd55]/20">
                <h4 class="text-xl font-semibold text-white mb-3">🏷️ Premium Identity</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  <strong>Custom NIP-05 @nostr.build verification</strong> - establish your premium identity on Nostr with official verification that builds trust and credibility.
                </p>
              </div>

              <div class="bg-gradient-to-r from-[#dabd55]/10 to-[#f78533]/10 rounded-xl p-6 border border-[#dabd55]/20">
                <h4 class="text-xl font-semibold text-white mb-3">📈 Expandable Infrastructure</h4>
                <p class="text-[#d0bed8] leading-relaxed">
                  <strong>Future-ready with expandable storage options</strong> and priority access to new features. Your account grows with your needs.
                </p>
              </div>

              <div class="mt-8 p-4 bg-[#dabd55]/5 rounded-lg border-l-4 border-[#dabd55]">
                <p class="text-sm text-[#dabd55] font-semibold">
                  💭 "I need enterprise-grade infrastructure with cutting-edge features and room to scale"
                </p>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- Reliability & Trust Section -->
    <section class="py-20 px-4 w-full">
      <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16 scroll-reveal">
          <h2 class="text-4xl md:text-6xl font-bold gradient-text mb-6">Built to Last, Built to Scale</h2>
          <p class="text-xl text-[#a58ead] max-w-3xl mx-auto">
            Your content deserves infrastructure you can trust. We're committed to excellence in every aspect of our service.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <!-- Continuous Innovation -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal">
            <div class="w-16 h-16 bg-gradient-to-r from-[#2edf95] to-[#07847c] rounded-2xl flex items-center justify-center mb-6">
              <div class="text-3xl">🚀</div>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Always Evolving</h3>
            <p class="text-[#d0bed8] leading-relaxed">
              Our team continuously develops new features and improvements. You're not just getting today's platform – you're investing in tomorrow's capabilities.
            </p>
          </div>

          <!-- Expert Team -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal">
            <div class="w-16 h-16 bg-gradient-to-r from-[#884ea4] to-[#292556] rounded-2xl flex items-center justify-center mb-6">
              <div class="text-3xl">👥</div>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Expert Craftsmanship</h3>
            <p class="text-[#d0bed8] leading-relaxed">
              Industry professionals with decades of experience work tirelessly to deliver the most reliable and efficient media hosting platform available.
            </p>
          </div>

          <!-- 24/7 Monitoring -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal">
            <div class="w-16 h-16 bg-gradient-to-r from-[#d251d5] to-[#8a0578] rounded-2xl flex items-center justify-center mb-6">
              <div class="text-3xl">⚡</div>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Round-the-Clock Protection</h3>
            <p class="text-[#d0bed8] leading-relaxed">
              Our systems are monitored 24/7 with instant response protocols. Your content stays online and accessible when you need it most.
            </p>
          </div>

          <!-- Data Protection -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal">
            <div class="w-16 h-16 bg-gradient-to-r from-[#dabd55] to-[#f78533] rounded-2xl flex items-center justify-center mb-6">
              <div class="text-3xl">🛡️</div>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Your Content, Protected</h3>
            <p class="text-[#d0bed8] leading-relaxed">
              Multiple backup systems ensure your media never disappears. Even if you need a break, your content stays safe and available for a full year after your account expires.
            </p>
          </div>

          <!-- High Availability -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal">
            <div class="w-16 h-16 bg-gradient-to-r from-[#2edf95] to-[#884ea4] rounded-2xl flex items-center justify-center mb-6">
              <div class="text-3xl">🎯</div>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Maximum Uptime</h3>
            <p class="text-[#d0bed8] leading-relaxed">
              Built with redundancy and reliability at its core. Our infrastructure is designed to keep your content accessible no matter what happens.
            </p>
          </div>

          <!-- Peace of Mind -->
          <div class="feature-card rounded-3xl p-8 scroll-reveal">
            <div class="w-16 h-16 bg-gradient-to-r from-[#884ea4] to-[#d251d5] rounded-2xl flex items-center justify-center mb-6">
              <div class="text-3xl">💎</div>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Complete Peace of Mind</h3>
            <p class="text-[#d0bed8] leading-relaxed">
              Focus on creating amazing content while we handle the technical complexity. Your success is our mission, and reliability is our foundation.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 px-4 w-full">
      <div class="max-w-4xl mx-auto text-center scroll-reveal">
        <h2 class="text-4xl md:text-6xl font-bold gradient-text pb-5 mb-8">Ready to Get Started?</h2>
        <p class="text-xl text-[#a58ead] mb-12 max-w-2xl mx-auto">
          Join thousands of creators who trust nostr.build for their media hosting needs
        </p>
        <div class="flex justify-center">
          <a href="/plans/" class="inline-block px-12 py-4 bg-gradient-to-r from-[#884ea4] to-[#292556] text-white font-semibold rounded-xl hover:shadow-lg transform hover:-translate-y-1 transition-all duration-300 text-lg">
            Choose Your Plan
          </a>
        </div>
      </div>
    </section>

  </main>

  <footer class="relative z-20">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/components/footer.php'; ?>
  </footer>

  <script>
    // Simple and clean scroll animations - no complex background management
    const observerOptions = {
      threshold: 0.15,
      rootMargin: '100px 0px -100px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('revealed');
        } else {
          entry.target.classList.remove('revealed');
        }
      });
    }, observerOptions);

    // Beautiful Analytics Carousel with Fade Effect
    function initAnalyticsCarousel() {
      const carousel = document.getElementById('analyticsCarousel');
      const container = carousel?.closest('.carousel-container');

      if (!carousel || !container) return;

      const slides = carousel.querySelectorAll('.carousel-slide');
      const totalSlides = slides.length;
      let currentSlide = 0;
      let isPlaying = true;
      let intervalId = null;

      // Check for reduced motion preference
      const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      if (prefersReducedMotion) {
        // Show first slide only for reduced motion
        slides.forEach((slide, index) => {
          if (index === 0) {
            slide.classList.add('active');
          } else {
            slide.classList.remove('active');
          }
        });
        return;
      }

      function goToSlide(slideIndex) {
        // Ensure we stay within bounds
        if (slideIndex >= totalSlides) {
          slideIndex = 0;
        } else if (slideIndex < 0) {
          slideIndex = totalSlides - 1;
        }

        // Remove active class from current slide
        slides[currentSlide].classList.remove('active');

        // Update current slide and add active class
        currentSlide = slideIndex;
        slides[currentSlide].classList.add('active');
      }

      function nextSlide() {
        if (!isPlaying) return;
        goToSlide(currentSlide + 1);
      }

      function startCarousel() {
        if (intervalId) clearInterval(intervalId);

        // 1.5s fade transition + 3.5s display = 5 seconds total per slide
        intervalId = setInterval(nextSlide, 5000);
      }

      function stopCarousel() {
        if (intervalId) {
          clearInterval(intervalId);
          intervalId = null;
        }
      }

      // Pause on hover, resume on leave
      container.addEventListener('mouseenter', () => {
        isPlaying = false;
        stopCarousel();
      });

      container.addEventListener('mouseleave', () => {
        isPlaying = true;
        startCarousel();
      });

      // Handle visibility change (pause when tab is hidden)
      document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
          stopCarousel();
        } else if (isPlaying) {
          startCarousel();
        }
      });

      // Start the carousel
      startCarousel();
    }

    // Beautiful AI Studio Carousel with Fade Effect
    function initAIStudioCarousel() {
      const carousel = document.getElementById('aiStudioCarousel');
      const container = carousel?.closest('.carousel-container');

      if (!carousel || !container) return;

      const slides = carousel.querySelectorAll('.carousel-slide');
      const totalSlides = slides.length;
      let currentSlide = 0;
      let isPlaying = true;
      let intervalId = null;

      // Check for reduced motion preference
      const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      if (prefersReducedMotion) {
        // Show first slide only for reduced motion
        slides.forEach((slide, index) => {
          if (index === 0) {
            slide.classList.add('active');
          } else {
            slide.classList.remove('active');
          }
        });
        return;
      }

      function goToSlide(slideIndex) {
        // Ensure we stay within bounds
        if (slideIndex >= totalSlides) {
          slideIndex = 0;
        } else if (slideIndex < 0) {
          slideIndex = totalSlides - 1;
        }

        // Remove active class from current slide
        slides[currentSlide].classList.remove('active');

        // Update current slide and add active class
        currentSlide = slideIndex;
        slides[currentSlide].classList.add('active');
      }

      function nextSlide() {
        if (!isPlaying) return;
        goToSlide(currentSlide + 1);
      }

      function startCarousel() {
        if (intervalId) clearInterval(intervalId);

        // 1.5s fade transition + 3.5s display = 5 seconds total per slide
        intervalId = setInterval(nextSlide, 5000);
      }

      function stopCarousel() {
        if (intervalId) {
          clearInterval(intervalId);
          intervalId = null;
        }
      }

      // Pause on hover, resume on leave
      container.addEventListener('mouseenter', () => {
        isPlaying = false;
        stopCarousel();
      });

      container.addEventListener('mouseleave', () => {
        isPlaying = true;
        startCarousel();
      });

      // Handle visibility change (pause when tab is hidden)
      document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
          stopCarousel();
        } else if (isPlaying) {
          startCarousel();
        }
      });

      // Start the carousel
      startCarousel();
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
      // Observe scroll-reveal elements
      const scrollElements = document.querySelectorAll('.scroll-reveal');
      scrollElements.forEach(el => observer.observe(el));

      // Initialize the analytics carousel
      initAnalyticsCarousel();

      // Initialize the AI Studio carousel
      initAIStudioCarousel();

      // Smooth scrolling for anchor links
      document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
          e.preventDefault();
          const target = document.querySelector(this.getAttribute('href'));
          if (target) {
            target.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
          }
        });
      });
    });
  </script>
</body>

</html>