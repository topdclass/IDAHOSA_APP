<?php
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])); 
$appBase = dirname($scriptDir); 
if ($appBase === '/' || $appBase === '\\') $appBase = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Get Started - RosmonSMS</title>
    <!-- Modern Styling -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .gradient-bg { background: linear-gradient(135deg, #13198f 0%, #1e3a8a 100%); }
        
        /* Input Styling */
        .input-modern { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; outline: none; transition: 0.2s; width: 100%; font-size: 15px; color: #1e293b; background: #fff; }
        .input-modern:focus { border-color: #1d4ed8; box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.1); background: #f8fafc; }
        .input-wrapper { position: relative; }
        .input-icon { position: absolute; left: 16px; top: 14px; color: #94a3b8; font-size: 16px; }
        .input-with-icon { padding-left: 44px; }
        
        /* Button */
        .btn-modern { background-color: #13198f; color: white; padding: 14px; border-radius: 8px; font-weight: 600; width: 100%; transition: all 0.2s; box-shadow: 0 4px 12px rgba(19, 25, 143, 0.2); display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-modern:hover { background-color: #0e1373; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(19, 25, 143, 0.3); }
        .btn-modern:active { transform: translateY(0); }
        
        /* Package Selector Details */
        select.input-modern { appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 1rem center; background-size: 1em; }
        
        /* Decorations */
        .glass-box { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 16px; }
        .circle-blur { position: absolute; border-radius: 50%; filter: blur(60px); opacity: 0.4; z-index: 0; }
        .circle-1 { width: 300px; height: 300px; background: #3b82f6; top: -100px; left: -100px; }
        .circle-2 { width: 250px; height: 250px; background: #818cf8; bottom: -50px; right: -50px; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4 sm:p-6 lg:p-8 relative">

    <div class="max-w-6xl w-full bg-white rounded-3xl shadow-2xl overflow-hidden flex flex-col md:flex-row relative z-10 border border-gray-100">
        
        <!-- Left Sidebar: Branding & Value Props -->
        <div class="md:w-5/12 gradient-bg text-white p-10 lg:p-14 flex flex-col justify-between relative overflow-hidden hidden md:flex">
            <div class="circle-blur circle-1"></div>
            <div class="circle-blur circle-2"></div>
            
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-12">
                    <div class="w-10 h-10 bg-white text-blue-900 rounded-full flex items-center justify-center text-xl shadow-lg">
                        <i class="fa-solid fa-book-open"></i>
                    </div>
                    <span class="text-xl font-bold tracking-wider text-white">ROSMON SMS</span>
                </div>
                
                <h1 class="text-4xl font-extrabold leading-tight mb-6">
                    Launch Your Digital Campus Today.
                </h1>
                <p class="text-blue-100 text-lg mb-10 leading-relaxed font-medium">
                    Centralize your school's structural academics, real-time computer-based testing, and profound financial ledgers into a single robust SaaS platform.
                </p>
                
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="mt-1 w-8 h-8 rounded-full bg-blue-800 bg-opacity-50 flex items-center justify-center flex-shrink-0 text-blue-200">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg">Multi-Tier Result Approvals</h3>
                            <p class="text-blue-200 text-sm mt-1">Strict workflows from Subject Teachers to Principal verification.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="mt-1 w-8 h-8 rounded-full bg-blue-800 bg-opacity-50 flex items-center justify-center flex-shrink-0 text-blue-200">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg">WAEC-Style CBT Interface</h3>
                            <p class="text-blue-200 text-sm mt-1">High-end interactive objective testing engine with live timers.</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="mt-1 w-8 h-8 rounded-full bg-blue-800 bg-opacity-50 flex items-center justify-center flex-shrink-0 text-blue-200">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg">Automated Financial P&L</h3>
                            <p class="text-blue-200 text-sm mt-1">Monitor fee debtors and auto-calculate gross & net profit margins.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="relative z-10 mt-12 glass-box p-6 flex items-center gap-4">
                <div class="text-4xl text-yellow-400"><i class="fa-solid fa-shield-halved"></i></div>
                <div>
                    <h4 class="font-bold">Enterprise Grade Security</h4>
                    <p class="text-xs text-blue-100 mt-1">Dedicated, fully isolated cloud databases generated for every tenant institution.</p>
                </div>
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="md:w-7/12 p-8 sm:p-12 lg:p-16 bg-white relative">
            
            <!-- Mobile Logo -->
            <div class="flex items-center gap-3 mb-8 md:hidden">
                <div class="w-10 h-10 bg-blue-900 text-white rounded-full flex items-center justify-center text-xl">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <span class="text-xl font-bold tracking-wider text-blue-900">ROSMON SMS</span>
            </div>

            <div>
                <h2 class="text-3xl font-extrabold text-gray-900 mb-2">School Registration Area</h2>
                <p class="text-gray-500 mb-8 font-medium">Select a package, enter your institution's authoritative details, and generate your isolated local environment.</p>
            </div>
            
            <?php if(isset($_GET['success'])): ?>
                <div style="background: linear-gradient(135deg, #d1fae5, #ecfdf5); border: 1px solid #6ee7b7; border-left: 5px solid #10b981; padding: 20px 24px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(16,185,129,0.1);">
                    <div style="display:flex; align-items:flex-start; gap:16px;">
                        <div style="width:48px; height:48px; background:#10b981; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i class="fa-solid fa-circle-check" style="color:white; font-size:22px;"></i>
                        </div>
                        <div>
                            <p style="font-size:16px; font-weight:800; color:#065f46; margin:0 0 6px;">
                                🎉 Registration Submitted Successfully!
                            </p>
                            <p style="font-size:14px; color:#047857; margin:0; line-height:1.7;">
                                Thank you for registering with <strong>RosmonSMS</strong>. Your school portal is currently being reviewed.<br>
                                Once your account is approved, <strong>RosmonSMS will create your school portal</strong> and send your login details directly to your registered email address.<br><br>
                                <strong>📧 Please check your email inbox</strong> (and spam folder) for your login credentials after approval.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <form action="<?= $appBase ?>/api/register-school" method="POST" class="space-y-5">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Institution Name</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-graduation-cap input-icon"></i>
                            <input name="school_name" type="text" required class="input-modern input-with-icon" placeholder="Christland Int. School">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Official Administrator</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user-tie input-icon"></i>
                            <input name="admin_name" type="text" required class="input-modern input-with-icon" placeholder="John Doe">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Administrative Email</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-envelope input-icon"></i>
                            <input name="email" type="email" required class="input-modern input-with-icon" placeholder="admin@christland.edu">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Contact Line</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-phone input-icon"></i>
                            <input name="phone" type="text" required class="input-modern input-with-icon" placeholder="+234 801 234 5678">
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">SaaS License Package</label>
                    <div class="input-wrapper">
                        <i class="fa-solid fa-box-open input-icon" style="z-index:10;"></i>
                        <select name="package" class="input-modern input-with-icon relative" style="cursor: pointer;">
                            <option value="Basic">Basic Essentials - ₦50,000 / Session</option>
                            <option value="Premium">Premium (CBT & Advanced Finance) - ₦150,000 / Session</option>
                            <option value="Enterprise">Enterprise Multi-Branch - Custom Pricing</option>
                        </select>
                    </div>
                </div>

                <!-- Payment Instructions Box -->
                <div class="mt-8 bg-gray-50 border border-gray-200 rounded-xl p-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-24 h-24 bg-blue-100 rounded-bl-full opacity-50 -mr-6 -mt-6"></div>
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-building-columns"></i>
                            </div>
                            <h4 class="font-bold text-gray-800 text-lg">Bank Transfer Authentication</h4>
                        </div>
                        <p class="text-sm text-gray-600 mb-4 font-medium">Please process payment for your selected license tier to the official corporate account down below before finalizing submission.</p>
                        
                        <div class="bg-white border border-gray-300 rounded-lg p-4 font-mono text-gray-800 shadow-inner flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <div class="text-xs text-gray-500 uppercase tracking-wider mb-1">Corporate Account</div>
                                <div class="font-bold text-lg text-blue-900">0123456789</div>
                                <div class="text-sm font-semibold mt-1">Guaranty Trust Bank (GTB)</div>
                                <div class="text-sm">Rosmon Technologies Ltd</div>
                            </div>
                            <div class="text-4xl text-gray-200">
                                <i class="fa-brands fa-cc-mastercard"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pt-6">
                    <button type="submit" class="btn-modern">
                        Submit License Application <i class="fa-solid fa-bolt ml-1"></i>
                    </button>
                </div>
                
                <div class="text-center mt-6">
                    <a href="<?= $appBase ?>/" class="text-sm font-semibold text-blue-600 hover:text-blue-800 transition-colors inline-flex items-center gap-2">
                        <i class="fa-solid fa-arrow-left"></i> Return to Gateway Login
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
