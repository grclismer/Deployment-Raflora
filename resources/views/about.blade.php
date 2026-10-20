<x-app-layout>
    <style>
        /* Force ABOUT nav link to be purple to match target design without modifying shared component */
        header a[href="{{ route('about') }}"] {
            color: #1E7E34 !important; 
            border-bottom-color: #1E7E34 !important;
        }
        #mobileMenuPanel a[href="{{ route('about') }}"] {
            color: #1E7E34 !important;
            background-color: rgba(126, 34, 206, 0.1) !important;
        }
        .btn-our-story {
            background-color: #008955 !important;
            color: white !important;
            border: none !important;
        }
        .btn-our-story:hover {
            background-color: #007044 !important;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0, 137, 85, 0.3), 0 4px 6px -2px rgba(0, 137, 85, 0.2) !important;
        }
    </style>
    <x-navbar title="ABOUT" />
    <main class="relative bg-[#FCF9F9] overflow-x-hidden font-sans text-slate-700">
        <!-- Hero Section -->
        <div class="relative w-full min-h-[400px] md:min-h-[500px] flex items-center mb-12">
            <!-- Background Image -->
            <div class="absolute inset-0 z-0">
                <img src="{{ asset('assets/images/about/about-us-header.png') }}" class="w-full h-full object-cover object-center" alt="Hero Floral Setup">
            </div>
            <!-- Gradient Overlay for Readability -->
            <div class="absolute inset-0 bg-gradient-to-r from-white/95 md:from-white/70 via-white/40 to-transparent z-10 pointer-events-none"></div>
            
            <!-- Content -->
            <div class="relative z-20 w-full max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-12">
                <div class="max-w-2xl py-12 md:py-16">
                    <h3 class="text-[#9F7A9B] uppercase tracking-[0.15em] text-xs font-bold mb-4">About Raflora</h3>
                    <h1 class="font-serif text-4xl sm:text-5xl md:text-[3.5rem] text-[#1B1B3A] font-bold mb-6 leading-[1.1]">Bringing Life and Color<br>to Every Celebration</h1>
                    <p class="text-slate-800 mb-8 leading-relaxed text-sm sm:text-base max-w-lg font-medium">
                        Raflora Enterprises has been creating beautiful floral designs and event setups since 2015. What started as a small family venture has now grown into a trusted name in the Philippine event industry.
                    </p>
                    <div>
                        <a href="#meet-the-team" class="inline-flex items-center gap-2 btn-our-story px-6 py-3 rounded-full font-semibold transition duration-300 text-sm shadow-md">
                            Meet the Team <i class="fa-solid fa-chevron-down text-xs ml-1 mt-[2px]"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-12 pb-8 sm:pb-12">

            <!-- Meet the People Behind Raflora Section -->
            <div id="meet-the-team" class="bg-white p-6 sm:p-10 shadow-sm border border-slate-100 mb-12" style="border-radius: 32px;">
                <div class="flex flex-col lg:flex-row gap-12 lg:gap-20">
                    <!-- Left Side -->
                    <div class="w-full lg:w-[55%] flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-4 mb-4">
                                <h3 class="uppercase text-xs font-bold" style="color: #9F7A9B; letter-spacing: 0.15em;">Meet the People Behind Raflora</h3>
                                <div class="h-px" style="background-color: #D6A8C2; width: 40px;"></div>
                            </div>
                            <h2 class="font-serif text-3xl md:text-4xl font-bold mb-4 leading-tight" style="color: #1B1B3A;">Family, Passion, and Creativity</h2>
                            <p class="text-slate-600 leading-relaxed text-sm md:text-base" style="font-size: 15px;">
                                Raflora is driven by a family that shares a passion for flowers, design, and meaningful celebrations. Together, we combine experience, creativity, and dedication to make every event special.
                            </p>
                        </div>
                        <div class="mt-auto">
                            <!-- Using section1.jpg which is likely the group photo -->
                            <img src="{{ asset('assets/images/about/section1.jpg') }}" class="w-full object-cover object-top shadow-sm" style="height: 340px; border-radius: 24px;" alt="The Raflora Family">
                        </div>
                    </div>
                    
                    <!-- Right Side (Profiles) -->
                    <div class="w-full lg:w-[45%] flex flex-col justify-center" style="gap: 40px;">
                        <!-- Antonio Profile -->
                        <div class="flex flex-col sm:flex-row items-start" style="gap: 24px;">
                            <img src="{{ asset('assets/images/about/antionio.jpg') }}" class="object-cover object-center shadow-sm" style="width: 200px; height: 200px; border-radius: 20px; flex-shrink: 0;" alt="Antonio A. Adriatico Jr.">
                            <div class="mt-2">
                                <h3 class="font-serif font-bold text-xl lg:text-2xl mb-1" style="color: #1B1B3A;">Antonio A. Adriatico Jr.</h3>
                                <p class="uppercase font-semibold mb-3 italic" style="color: #9F7A9B; letter-spacing: 0.15em; font-size: 14px;">Creative Director</p>
                                <p class="text-slate-600 leading-relaxed text-sm md:text-base" style="font-size: 15px;">
                                    Leads the creative direction of Raflora, bringing years of experience and a deep passion for floral design and event styling.
                                </p>
                            </div>
                        </div>

                        <!-- Raffy Profile -->
                        <div class="flex flex-col sm:flex-row items-start" style="gap: 24px;">
                            <img src="{{ asset('assets/images/about/Rafael.jpg') }}" class="object-cover object-top shadow-sm" style="width: 200px; height: 200px; border-radius: 20px; flex-shrink: 0;" alt="Raffy Christian Zamora">
                            <div class="mt-2">
                                <h3 class="font-serif font-bold text-xl lg:text-2xl mb-1" style="color: #1B1B3A;">Raffy Christian Zamora</h3>
                                <p class="uppercase font-semibold mb-3 italic" style="color: #9F7A9B; letter-spacing: 0.15em; font-size: 14px;">Proprietor</p>
                                <p class="text-slate-600 leading-normal" style="font-size: 15px;">
                                    Oversees the overall operations of Raflora, ensuring quality service, client satisfaction, and continuous growth of the business.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- What We Believe In (Values) Section -->
            <div class="bg-white p-6 sm:p-10 shadow-sm border border-slate-100 mb-1" style="border-radius: 32px;">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-6 mb-10">
                    <div>
                        <div class="flex items-center gap-4 mb-4">
                            <h3 class="uppercase tracking-[0.15em] text-xs font-bold" style="color: #9F7A9B;">What We Believe In</h3>
                            <div class="h-px" style="background-color: #D6A8C2; width: 40px;"></div>
                        </div>
                        <h2 class="font-serif text-3xl md:text-4xl font-bold" style="color: #1B1B3A;">Our Values</h2>
                    </div>
                    <div class="md:max-w-xs pb-1">
                        <p class="text-slate-600 text-sm">These values guide us in every event we create and in every client we serve.</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 lg:gap-8 mt-4">
                    <!-- Quality -->
                    <div class="flex flex-row gap-3 lg:gap-4 items-center">
                        <div class="w-12 h-12 lg:w-16 lg:h-16 shrink-0 rounded-full flex items-center justify-center" style="background-color: #FDF2F5; color: #E96695;">
                            <i class="fa-regular fa-heart text-xl lg:text-2xl"></i>
                        </div>
                        <div>
                            <div class="font-serif font-bold text-lg mb-1" style="color: #1B1B3A;">Quality</div>
                            <div class="text-slate-600 leading-relaxed" style="font-size: 15px;">We use fresh, high-quality flowers and reliable materials.</div>
                        </div>
                    </div>
                    <!-- Creativity -->
                    <div class="flex flex-row gap-3 lg:gap-4 items-center">
                        <div class="w-12 h-12 lg:w-16 lg:h-16 shrink-0 rounded-full flex items-center justify-center" style="background-color: #E8F5EE; color: #008955;">
                            <i class="fa-solid fa-leaf text-xl lg:text-2xl"></i>
                        </div>
                        <div>
                            <div class="font-serif font-bold text-lg mb-1" style="color: #1B1B3A;">Creativity</div>
                            <div class="text-slate-600 leading-relaxed" style="font-size: 15px;">We design with passion and attention to detail.</div>
                        </div>
                    </div>
                    <!-- Customer Focus -->
                    <div class="flex flex-row gap-3 lg:gap-4 items-center">
                        <div class="w-12 h-12 lg:w-16 lg:h-16 shrink-0 rounded-full flex items-center justify-center" style="background-color: #F4EEFB; color: #8B5CF6;">
                            <i class="fa-solid fa-user-group text-xl lg:text-2xl"></i>
                        </div>
                        <div>
                            <div class="font-serif font-bold text-lg mb-1" style="color: #1B1B3A;">Customer Focus</div>
                            <div class="text-slate-600 leading-relaxed" style="font-size: 15px;">We listen, understand, and bring your vision to life.</div>
                        </div>
                    </div>
                    <!-- Trust -->
                    <div class="flex flex-row gap-3 lg:gap-4 items-center">
                        <div class="w-12 h-12 lg:w-16 lg:h-16 shrink-0 rounded-full flex items-center justify-center" style="background-color: #FFF6EB; color: #F59E0B;">
                            <i class="fa-regular fa-star text-xl lg:text-2xl"></i>
                        </div>
                        <div>
                            <div class="font-serif font-bold text-lg mb-1" style="color: #1B1B3A;">Trust</div>
                            <div class="text-slate-600 leading-relaxed" style="font-size: 15px;">We build lasting relationships through honest and reliable service.</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Bottom CTA Section (Full Width) -->
        <div class="relative w-full overflow-hidden text-center flex flex-col items-center justify-center shadow-sm" style="padding: 40px 24px;">
            <div class="absolute inset-0 z-0">
                <img src="{{ asset('assets/images/about/about-us-footer.png') }}" class="w-full h-full object-cover object-center" alt="Floral Pattern">
            </div>
            
            <div class="relative z-10 max-w-3xl mx-auto flex flex-col items-center">
                <h3 class="uppercase text-xs font-bold mb-4" style="color: #E96695; letter-spacing: 0.15em;">READY TO CREATE YOUR SPECIAL EVENT?</h3>
                <h2 class="font-serif text-3xl sm:text-4xl md:text-5xl font-bold mb-6" style="color: #1B1B3A;">Let's Make Your Event Unforgettable</h2>
                <p class="text-slate-800 mb-8 text-sm md:text-base max-w-xl leading-relaxed font-semibold">From intimate gatherings to grand celebrations, Raflora is here to bring your vision to life with beautiful floral designs and event setups.</p>
                <a href="{{ route('packages.index') }}" class="inline-flex items-center gap-3 btn-our-story px-6 md:px-12 py-3 rounded-full font-bold text-base md:text-lg transition-all duration-300 shadow-lg hover:-translate-y-1">
                    View Our Packages <i class="fa-solid fa-arrow-right text-sm md:text-base mt-[1px]"></i>
                </a>
            </div>
        </div>
    </main>
</x-app-layout>
