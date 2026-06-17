<?php
declare(strict_types=1);

function dent_exams_term6_reference_course_data_craig_dental_materials_6_10(): array
{
    return [
        'slug' => 'craig-dental-materials-6-10',
        'paymentGroupSlug' => 'craig-dental-materials',
        'visibleOnCatalog' => false,
        'title' => 'کریگ - فصول ۶ تا ۱۰',
        'shortTitle' => 'فصول ۶ تا ۱۰',
        'badge' => '۱۰ آزمون',
        'cardDescription' => 'این بخش ۱۰ آزمون از فصول ۶ تا ۱۰ را در بر می‌گیرد.',
        'heroTitle' => 'کریگ - فصول ۶ تا ۱۰',
        'heroDescription' => 'این بخش برای مواد دندانی ترم ۶ آماده شده و ۱۰ آزمون با مجموع ۴۰۰ سؤال دارد. با خرید کامل مرجع کریگ این بخش هم برای همین حساب فعال می‌شود.',
        'path' => '/exams/craig-dental-materials/6-10/',
        'paymentTitle' => 'دسترسی کامل به آزمون‌های کریگ',
        'paymentDescription' => 'با یک بار پرداخت ۳۰ هزار تومان، همهٔ بخش‌های آزمون‌های مرجع کریگ برای همین حساب فعال می‌شود.',
        'paymentSuccessMessage' => 'پرداخت شما تایید شد و همهٔ بخش‌های آزمون‌های کریگ برای این حساب باز شد.',
        'paymentFailureMessage' => 'فعال‌سازی کامل آزمون‌های کریگ انجام نشد. نتیجه را دوباره بررسی کنید.',
        'defaultPaymentMode' => 'paid',
        'defaultAmount' => 300000,
        'exams' => [
            [
                'slug' => '6-1',
                'path' => '/exams/craig-dental-materials/6-10/6-1/',
                'questionCount' => 40,
                'label' => 'فصل ۶ - قسمت اول',
                'title' => 'آزمون فصل ۶ - قسمت اول',
                'subtitle' => '۴۰ سؤال چهارگزینه‌ای از مجموعه کریگ.',
                'description' => 'مرور ۴۰ سؤال از مجموعه کریگ.',
                'eyebrow' => 'کریگ | فصول ۶ تا ۱۰ | فصل ۶ - قسمت اول',
                'backHref' => '/exams/craig-dental-materials/6-10/',
                'backLabel' => 'بازگشت به فصول ۶ تا ۱۰ کریگ',
                'autoAdvance' => true,
                'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'siteSubtitle' => 'آزمون‌ها',
                'siteBadge' => 'آزمون رفرنسی',
                'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'questions' => [
                    [
                        'question' => 'در ارزیابی یک ماده که برای crown کامل قابل قبول گزارش شده اما برای dental implant هنوز ارزیابی نشده است، کدام برداشت با تعریف فصل از biocompatibility سازگارتر است؟',
                        'options' => [
                            'اگر ماده در یک کاربرد بی خطر باشد، در همه کاربردهای دندانی biocompatible محسوب می شود.',
                            'داوری درباره biocompatibility بدون تعیین کاربرد و پاسخ زیستی مورد انتظار ناقص است.',
                            'سازگاری زیستی عمدتاً به استحکام ماده مربوط است و کاربرد بالینی نقش ثانویه دارد.',
                            'مواد فلزی تا زمانی که التهاب ایجاد نکنند برای هر کاربردی پاسخ زیستی مناسب دارند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: در تعریف فصل، biocompatibility به «کاربرد در بدن» و «پاسخ زیستی مناسب مورد انتظار» وابسته است. بنابراین باید معلوم باشد ماده برای crown، implant یا کاربرد دیگر استفاده می شود و چه پاسخی از آن انتظار می رود. رد گزینه ها: الف نادرست است چون یک ماده ممکن است در یک کاربرد قابل قبول و در کاربرد دیگر نامناسب باشد. ج نادرست است چون استحکام فقط یکی از ملاحظات است و پاسخ زیستی و کاربرد تعیین کننده اند. د نادرست است چون نبود التهاب برای crown کافی تر است، اما برای implant پاسخ هایی مانند osseointegration نیز مطرح می شود.',
                    ],
                    [
                        'question' => 'در یک آزمون غربالگری، هدف اصلی بررسی مرگ سلولی پس از تماس با ماده است و معیار، تغییر تعداد یا رشد سلول ها قبل و بعد از exposure است. این طراحی به کدام گروه نزدیک تر است؟',
                        'options' => [
                            'Mutagenesis assay',
                            'Dental pulp irritation usage test',
                            'Cytotoxicity test',
                            'Skin sensitization test',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: Cytotoxicity tests مرگ سلول را با تغییر cell number یا growth پیش و پس از exposure به ماده ارزیابی می کنند. رد گزینه ها: الف مربوط به اثر بر genetic material است. ب ماده را در موقعیت کاربردی pulp قرار می دهد و usage test است. د برای hypersensitivity پوستی در حیوان به کار می رود، نه شمارش یا رشد سلولی در cell culture.',
                    ],
                    [
                        'question' => 'در مقایسه سه نوع آزمون، کدام گزینه مزیت و ضعف اصلی usage test را بهتر نشان می دهد؟',
                        'options' => [
                            'سریع و ارزان است، اما ارتباط آن با کاربرد واقعی ماده نامشخص است.',
                            'بیشترین ارتباط را با کاربرد واقعی دارد، اما بسیار پرهزینه، زمان بر و دشوار از نظر کنترل و تفسیر است.',
                            'پاسخ سیستمیک پیچیده را نشان می دهد، اما چون ماده در کاربرد نهایی قرار نمی گیرد محدود است.',
                            'برای مکانیسم های سلولی عالی است، اما فاقد مکانیسم های التهابی و محافظتی بافتی است.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: usage tests از نظر relevance به کاربرد واقعی ماده gold standard محسوب می شوند، اما بسیار expensive، time consuming، دارای مسایل legal/ethical و دشوار از نظر control و interpretation هستند. رد گزینه ها: الف مزیت های in vitro tests را با ضعف in vivo relevance آن ها بیان می کند. ج عمدتاً درباره animal tests غیر usage است. د توصیف in vitro tests است.',
                    ],
                    [
                        'question' => 'در مطالعه کلاسیک مقایسه ZOE، resin composite و silicate cement، کدام الگوی نتایج به بهترین وجه نقش dentin barrier و محیط آزمون را نشان می دهد؟',
                        'options' => [
                            'ZOE در cell culture شدیدترین اثر را داشت، اما در pulp response تقریباً بی اثر بود.',
                            'Silicate در cell culture شدیدترین اثر را داشت، اما در pulp response تقریباً بی اثر بود.',
                            'Resin composite در همه آزمون ها واکنش صفر نشان داد.',
                            'ZOE در implantation و pulp response واکنش شدید یکسان نشان داد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: در جدول مقایسه ای، ZOE در cell culture واکنش شدید نشان داد اما pulp response آن صفر بود؛ این تفاوت با رقیق شدن مواد آزادشده، سد dentin و شرایط محیطی توضیح داده شد. رد گزینه ها: ب نادرست است؛ silicate در pulp response شدیدتر بود، نه بی اثر. ج نادرست است؛ resin composite در cell culture و implantation اثر متوسط داشت و pulp response خفیف بود. د نادرست است؛ ZOE در implantation خفیف و در pulp response صفر گزارش شد، نه شدید.',
                    ],
                    [
                        'question' => 'ماده ای قرار است با mucous membrane تماس داشته باشد و پژوهشگر می خواهد در حیوان، التهاب مخاط یا پوست ساییده شده را بسنجد، بدون اینکه ماده را در شرایط کاربرد نهایی قرار دهد. این آزمون کدام است؟',
                        'options' => [
                            'Mucous membrane irritation test',
                            'Dental pulp irritation usage test',
                            'Clinical trial',
                            'Agar overlay method',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: Mucous membrane irritation test در حیوان بررسی می کند که ماده آیا mucous membrane یا abraded skin را ملتهب می کند، بدون قرار دادن آن دقیقاً در کاربرد نهایی. رد گزینه ها: ب ماده را در class 5 cavity و روی pulp بررسی می کند. ج usage test انسانی است. د آزمون in vitro barrier با agar است.',
                    ],
                    [
                        'question' => 'مطابق ANSI/ADA specification No. 41، کدام ترکیب در دسته initial tests قرار می گیرد؟',
                        'options' => [
                            'Dermal irritation، hypersensitivity، bony implantation',
                            'Cytotoxicity، hemolysis، cellular mutagenesis/carcinogenesis، acute physiological distress/death',
                            'Subcutaneous implantation، usage test در primates، clinical trial',
                            'Osseointegration radiograph، mobility test، periodontal probing',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: در ANSI/ADA specification 41، initial tests شامل cytotoxicity، hemolysis، cellular mutagenesis/carcinogenesis و acute physiological distress/death هستند. رد گزینه ها: الف و ج به secondary یا usage tests نزدیک اند. د معیارهای implant usage test هستند، نه initial test استاندارد.',
                    ],
                    [
                        'question' => 'در آزمونی، سلول ها روی یک filter رشد داده شده اند، سپس filter برگردانده می شود و ماده روی آن قرار می گیرد تا diffusion products به سلول برسند. این توصیف مربوط به کدام روش است؟',
                        'options' => [
                            'Agar overlay method',
                            'Millipore filter assay',
                            'Ames test',
                            'Skin sensitization test',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: در Millipore filter assay، monolayer سلولی روی filter رشد می کند، filter برگردانده می شود و leachable diffusion products با سلول ها تعامل می کنند. رد گزینه ها: الف در agar overlay، agar بین سلول و ماده سد می سازد. ج mutagenesis test با Salmonella است. د آزمون hypersensitivity پوستی است.',
                    ],
                    [
                        'question' => 'اگر ماده ای خودش DNA را تغییر ندهد اما با تغییر biochemistry سلول یا سیستم ایمنی، رشد تومور را حمایت کند، در اصطلاح فصل کدام توصیف مناسب تر است؟',
                        'options' => [
                            'Genotoxic mutagen',
                            'Promutagen',
                            'Epigenetic mutagen',
                            'Nonbioactive ceramic',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: Epigenetic mutagens خود DNA را تغییر نمی دهند، اما با تغییر biochemistry، immune system، اثرات hormonal یا مکانیسم های دیگر رشد تومور را حمایت می کنند. رد گزینه ها: الف مستقیماً DNA را تغییر می دهد. ب ماده ای است که پس از activation/biotransformation mutagen می شود. د مربوط به ceramic implant و واکنش fibrous encapsulation است.',
                    ],
                    [
                        'question' => 'در پیگیری یک implant استخوانی، کدام مجموعه یافته ها با موفقیت implant سازگارتر است؟',
                        'options' => [
                            'وجود mobility خفیف، radiolucency اطراف implant، و bone loss عمودی کم',
                            'فقدان mobility، نبود radiographic periimplant radiolucency، bone loss عمودی حداقل و نبود عوارض نرم بافتی پایدار',
                            'fibrous capsule نازک، حرکت میکروسکوپی، و کاهش التهاب مخاطی',
                            'probing عمیق کنار implant، اما نبود درد و التهاب بالینی',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: موفقیت implant استخوانی با نبود mobility، نبود periimplant radiolucency، bone loss عمودی حداقل، encasement in bone و نبود soft-tissue complications پایدار تعریف می شود. رد گزینه ها: الف وجود mobility و radiolucency نشانه شکست یا مشکل است. ج fibrous capsule نشانه irritation و chronic inflammation است. د probing عمیق کنار implant معیار مطلوب موفقیت نیست.',
                    ],
                    [
                        'question' => 'برای استانداردسازی یک in vitro assay، استفاده از continuous cell line چه امتیاز اصلی نسبت به primary cells دارد؟',
                        'options' => [
                            'همیشه تمام ویژگی های in vivo را کامل تر حفظ می کند.',
                            'از نظر ژنتیکی و متابولیک پایدارتر است و به استانداردسازی روش کمک می کند.',
                            'فقط از یک فرد منشا می گیرد و بنابراین variability ژنتیکی بیشتری ایجاد می کند.',
                            'پس از ورود به culture سریع تر عملکرد in vivo خود را از دست می دهد.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: continuous cell lines به دلیل stability ژنتیکی و متابولیک، ویژگی های حفظ شده را پایدار نشان می دهند و standardization assay را بهتر می کنند. رد گزینه ها: الف نادرست است؛ primary cells معمولاً ویژگی های in vivo بیشتری حفظ می کنند. ج ویژگی primary cells است نه cell line. د محدودیت primary cells است.',
                    ],
                    [
                        'question' => 'در طرح های جدیدتر ارزیابی biocompatibility، کدام تغییر نسبت به مدل هرمی اولیه برجسته است؟',
                        'options' => [
                            'پس از clinical trial دیگر نیازی به primary یا secondary test وجود ندارد.',
                            'in vitro testها به دلیل نبود همبستگی با usage test حذف می شوند.',
                            'ارزیابی، فرایندی ongoing تلقی می شود و آزمون ها ممکن است در مراحل مختلف توسعه و استفاده بالینی دوباره به کار روند.',
                            'فقط usage test مجاز است، زیرا تنها آزمونی است که پاسخ قطعی می دهد.',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: طرح های جدیدتر تاکید دارند که primary، secondary و usage tests در طول development و حتی پس از ورود material به clinical service می توانند نقش ongoing داشته باشند. رد گزینه ها: الف نادرست است؛ آزمون های اولیه و ثانویه ممکن است پس از clinical evaluation هم لازم شوند. ب نادرست است؛ in vitro tests حذف نشده اند. د نادرست است؛ usage test مهم است اما به تنهایی کافی و عملی نیست.',
                    ],
                    [
                        'question' => 'در طراحی آزمون pulpal irritation، چرا حذف یا کنترل bacteria و products ناشی از microleakage ضروری است؟',
                        'options' => [
                            'چون bacteria فقط در آزمون های in vitro فعال می شوند.',
                            'چون pulpal irritation ممکن است از microleakage ناشی شود و به اشتباه به خود ماده نسبت داده شود.',
                            'چون dentin barrier همیشه ورود bacteria را کاملاً متوقف می کند.',
                            'چون همه مواد restorative در تماس مستقیم با pulp واکنش یکسان دارند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: مطالعات نشان داده اند که irritation pulp ممکن است به bacteria، bacterial products یا microleakage نسبت داده شود نه فقط restorative material؛ بنابراین آزمون باید این عوامل را حذف یا کنترل کند. رد گزینه ها: الف نادرست است؛ bacteria در شرایط in vivo و زیر restoration نیز اهمیت دارند. ج نادرست است؛ dentin barrier اثر را تعدیل می کند اما کامل و مطلق نیست. د نادرست است؛ مواد پاسخ های متفاوتی دارند.',
                    ],
                    [
                        'question' => 'کدام ویژگی، مزیت اختصاصی in vitro tests نسبت به animal و usage tests محسوب می شود؟',
                        'options' => [
                            'relevance تضمین شده به کاربرد نهایی ماده',
                            'امکان مشاهده کامل complex systemic interactions',
                            'سرعت، هزینه کمتر، standardization و کنترل تجربی بهتر',
                            'توانایی قطعی در پیش بینی overall biocompatibility',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: مزایای in vitro tests شامل quick performance، کم هزینه بودن، امکان standardization، screening وسیع و experimental control خوب است. رد گزینه ها: الف ویژگی usage tests است. ب مزیت animal tests است. د نادرست است؛ فصل تاکید دارد in vitro tests به تنهایی overall biocompatibility را کامل پیش بینی نمی کنند.',
                    ],
                    [
                        'question' => 'در dentin disk barrier test، نقش اصلی dentin disk چیست؟',
                        'options' => [
                            'ایجاد التهاب کنترل شده در pulp animal',
                            'شبیه سازی سد dentin و فراهم کردن diffusion جهت دار بین ماده و محیط جمع آوری',
                            'تبدیل genotoxic mutagen به promutagen',
                            'حذف کامل leachable components پیش از تماس با سلول',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: dentin disk barrier test سد dentin را شبیه سازی می کند؛ ماده در یک طرف disk قرار می گیرد و diffusion components به سمت collection fluid یا cells در طرف دیگر سنجیده می شود. رد گزینه ها: الف مربوط به usage/animal pulp model است. ج مربوط به mutagenesis نیست. د نادرست است؛ هدف حذف کامل leachable components نیست، بلکه سنجش عبور آن هاست.',
                    ],
                    [
                        'question' => 'اصطلاح clinical trial در فصل برای کدام وضعیت به کار می رود؟',
                        'options' => [
                            'هر آزمون in vitro با سلول انسانی',
                            'usage test هنگامی که در انسان انجام شود',
                            'implantation test در حیوان بزرگ',
                            'animal test که ماده در کاربرد نهایی قرار نگرفته باشد',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: در فصل، usage test وقتی در humans انجام شود clinical trial نامیده می شود. رد گزینه ها: الف in vitro test حتی با سلول انسانی clinical trial نیست. ج implantation در حیوان usage test انسانی نیست. د animal test غیر usage با clinical trial متفاوت است.',
                    ],
                    [
                        'question' => 'در غربالگری mutagenesis، چرا Ames test در یک برنامه screening غالباً انتخاب می شود؟',
                        'options' => [
                            'چون تنها آزمون کوتاه مدت کاملاً اعتبارسنجی شده معرفی شده و انجام آن از نظر فنی آسان تر است.',
                            'چون مستقیماً روی mammalian cells انجام می شود و Salmonella در آن نقشی ندارد.',
                            'چون carcinogenesis in vivo را بدون نیاز به تفسیر اضافی اندازه می گیرد.',
                            'چون epigenetic mutagenها را از genotoxic mutagenها جدا نمی کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: Ames test پرکاربردترین short-term mutagenesis test و تنها آزمون thoroughly validated معرفی شده است و چون از نظر فنی آسان تر و در literature گسترده تر است، در screening رایج است. رد گزینه ها: ب توصیف Styles’ cell transformation test به عنوان آزمون mammalian cell است. ج carcinogenesis in vivo را مستقیم و بی نیاز از تفسیر اندازه نمی گیرد. د دلیل انتخاب Ames نیست.',
                    ],
                    [
                        'question' => 'در usage test مربوط به gingival tissues، کدام عامل تفسیر واکنش ماده را دشوارتر می کند؟',
                        'options' => [
                            'نبود هرگونه التهاب پیشین در gingiva',
                            'حضور plaque، roughness، open/overhanging margins یا contour نامناسب',
                            'قرارگیری ماده فقط در محیط cell culture',
                            'نبود mononuclear inflammatory cells در بافت',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: preexisting inflammation ناشی از plaque، roughness، open/overhanging margins و over/undercontouring تفسیر اثر واقعی ماده را دشوار می کند. رد گزینه ها: الف خلاف متن است. ج usage test در tissue است نه cell culture. د معیار دسته بندی پاسخ، حضور mononuclear inflammatory cells است.',
                    ],
                    [
                        'question' => 'تشبیه biocompatibility به color در فصل برای برجسته کردن کدام نکته است؟',
                        'options' => [
                            'biocompatibility یک ویژگی ثابت و مستقل از محیط است.',
                            'پاسخ زیستی به interaction ماده با host، application و خود material وابسته است.',
                            'color و biocompatibility هر دو فقط با ترکیب شیمیایی ماده تعیین می شوند.',
                            'observer در color مانند clinician در biocompatibility هیچ نقشی ندارد.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: فصل biocompatibility را مانند color وابسته به interaction با محیط می داند؛ biological response با تغییر host، application یا material تغییر می کند. رد گزینه ها: الف و ج وابستگی محیطی را نفی می کنند. د نادرست است؛ تشبیه نشان می دهد environment و interpretation نقش دارند.',
                    ],
                    [
                        'question' => 'در مدل هرمی اولیه، چرا تعداد مواد یا آزمون ها با حرکت به سمت بالای pyramid کاهش می یافت؟',
                        'options' => [
                            'چون مواد در مراحل پایین به طور نظری حذف می شدند و فقط مواد قبول شده به مراحل بالاتر می رفتند.',
                            'چون clinical trial از نظر هزینه ارزان تر از primary test بود.',
                            'چون specific toxicity testها همیشه پیش از unspecific toxicity انجام می شدند.',
                            'چون usage test در حیوان جایگزین همه آزمون های اولیه می شد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: در pyramid strategy، مواد ابتدا در آزمون های پایین تر بررسی و مواد نامطلوب حذف می شدند؛ بنابراین مواد/آزمون های لازم در مراحل بالاتر کمتر می شد. رد گزینه ها: ب خلاف متن است؛ clinical trials گران ترین و زمان برترین بودند. ج ترتیب را برعکس بیان می کند. د usage test جایگزین همه آزمون ها نبود.',
                    ],
                    [
                        'question' => 'در ISO 7405:2008، انتخاب نهایی آزمون های لازم برای یک material مشخص بر عهده چه کسی/گروهی گذاشته شده است؟',
                        'options' => [
                            'تولیدکننده، با الزام به ارایه و دفاع از نتایج آزمون ها',
                            'فقط ADA Council on Scientific Affairs',
                            'فقط پژوهشگر animal test بدون دخالت تولیدکننده',
                            'بیمار، بر اساس مدت حضور ماده در بدن',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: ISO 10993/7405 راهنما می دهد، اما ultimate selection of tests برای ماده مشخص بر عهده manufacturer است که باید testing results را ارایه و دفاع کند. رد گزینه ها: ب و ج انحصار نادرست ایجاد می کنند. د بیمار انتخاب کننده آزمون ها نیست.',
                    ],
                    [
                        'question' => 'Primary cells در in vitro assay چه محدودیتی دارند که در تفسیر نتایج مهم است؟',
                        'options' => [
                            'هیچ ویژگی in vivo را حفظ نمی کنند.',
                            'فقط برای مدت محدودی در culture رشد می کنند و ممکن است سریع عملکرد in vivo را از دست بدهند.',
                            'به علت transformation قبلی به طور نامحدود رشد می کنند.',
                            'برای cytotoxicity نسبت به continuous cell lines هیچ relevancy بیشتری ندارند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: Primary cells مستقیم از حیوان گرفته و culture می شوند، فقط مدت محدودی رشد می کنند و ممکن است عملکرد in vivo خود را سریع از دست بدهند. رد گزینه ها: الف نادرست است؛ معمولاً ویژگی های in vivo بیشتری حفظ می کنند. ج ویژگی continuous cell lines است. د نادرست است؛ primary cultures برای cytotoxicity مرتبط تر به نظر می رسند.',
                    ],
                    [
                        'question' => 'آزمون هایی مانند MTT، NBT، XTT و WST در کدام خوشه از in vitro tests قرار می گیرند؟',
                        'options' => [
                            'آزمون های نفوذ dye از غشای سلول',
                            'آزمون های colorimetric مبتنی بر فعالیت enzymatic/metabolic سلول',
                            'آزمون های clinical mobility implant',
                            'آزمون های skin hypersensitivity با adhesive patch',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: MTT، NBT، XTT و WST colorimetric assays مبتنی بر tetrazolium salts هستند که activity enzymatic/metabolic سلول را برای cytotoxic response می سنجند. رد گزینه ها: الف به membrane permeability dye tests مربوط است. ج implant usage test است. د skin sensitization animal test است.',
                    ],
                    [
                        'question' => 'تفاوت اصلی animal tests با usage tests انجام شده در حیوان چیست؟',
                        'options' => [
                            'در animal tests ماده در شرایط نهایی کاربرد بالینی خودش قرار نمی گیرد.',
                            'animal tests همیشه روی انسان انجام می شوند.',
                            'usage tests هیچ ارتباطی با شرایط کاربرد نهایی ندارند.',
                            'animal tests از نظر اخلاقی و قانونی هیچ نظارتی لازم ندارند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: animal tests با usage tests تفاوت دارند چون ماده در animal tests با توجه به final use خودش در حیوان قرار نمی گیرد. رد گزینه ها: ب نادرست است؛ animal tests روی حیوان انجام می شوند. ج نادرست است؛ usage tests باید شرایط کاربرد نهایی را mimic کنند. د نادرست است؛ ethical oversight از چالش های animal tests است.',
                    ],
                    [
                        'question' => 'در تبیین واکنش شدیدتر silicate cement در usage test نسبت به cell culture، کدام عامل در فصل ذکر شده است؟',
                        'options' => [
                            'diffusion gradient dentin غلظت eugenol را کاهش داده بود.',
                            'hydrogen ions آزادشده در cell culture و implantation احتمالاً buffered شدند، اما dentin در usage test آن ها را به خوبی buffer نکرد.',
                            'resin components باعث bactericidal seal شدند.',
                            'zinc ions و eugenol در cavity همه bacteria را حذف کردند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: silicate cement hydrogen ions آزاد می کرد که در cell culture و implantation احتمالاً buffered شد، اما در usage test dentin آن ها را به اندازه کافی buffer نکرد و واکنش شدیدتر شد. رد گزینه ها: الف درباره ZOE/eugenol است. ج مربوط به resin components نیست. د مربوط به ZOE است و توضیح silicate نیست.',
                    ],
                    [
                        'question' => 'در ISO 10993/7405، کدام مجموعه در initial tests ذکر شده است؟',
                        'options' => [
                            'Cytotoxicity، sensitization و systemic toxicity',
                            'Chronic toxicity، carcinogenicity و biodegradation',
                            'Clinical trial، long-term implant success و radiographs',
                            'Periodontal probing، mobility و plaque index',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: ISO standard tests را به initial و supplementary تقسیم می کند؛ initial tests شامل cytotoxicity، sensitization و systemic toxicity هستند. رد گزینه ها: ب در supplementary tests مطرح می شوند. ج و د معیارهای usage/clinical implant هستند نه ISO initial tests.',
                    ],
                    [
                        'question' => 'یک interface bonded از نظر کلی intact است، اما در collagen matrix دکلسیفیه، فضاهای بسیار کوچک باقی مانده که material در آن ها نفوذ نکرده است. leakage مورد بحث کدام است؟',
                        'options' => [
                            'Microleakage',
                            'Nanoleakage',
                            'Percolation فقط در amalgam',
                            'Hemolysis',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: Nanoleakage در dentin bonding و در فضاهای کوچک demineralized collagen matrix که bonded material نفوذ نکرده رخ می دهد، حتی وقتی overall bond intact باشد. رد گزینه ها: الف microleakage leakage بزرگ تر در gap restoration-tooth است. ج percolation در بحث thermal cycling amalgam آمده و معادل nanoleakage نیست. د hemolysis initial test است.',
                    ],
                    [
                        'question' => 'چرا in vitro tests به تنهایی برای پیش بینی overall biocompatibility کافی دانسته نمی شوند؟',
                        'options' => [
                            'چون relevance آن ها با in vivo questionable است و inflammatory/tissue-protective mechanisms را ندارند.',
                            'چون فقط در حیوانات کوچک انجام می شوند.',
                            'چون همیشه گران تر و زمان برتر از usage tests هستند.',
                            'چون هیچ وقت قابل استانداردسازی نیستند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: ضعف اصلی in vitro tests questionable relevance به in vivo و نبود inflammatory و tissue-protective mechanisms است؛ بنابراین به تنهایی overall biocompatibility را کامل پیش بینی نمی کنند. رد گزینه ها: ب نادرست است؛ در حیوان انجام نمی شوند. ج نادرست است؛ معمولاً ارزان تر و سریع ترند. د نادرست است؛ قابلیت standardization از مزایای آن هاست.',
                    ],
                    [
                        'question' => 'در usage testهای pulp irritation، چرا مدل هایی با induced pulpitis اهمیت بیشتری پیدا کرده اند؟',
                        'options' => [
                            'چون pulp ملتهب ممکن است به liners، cements و restorative agents متفاوت از pulp سالم پاسخ دهد.',
                            'چون pulp سالم در هیچ usage test قابل بررسی نیست.',
                            'چون induced pulpitis باعث حذف نیاز به microscopic examination می شود.',
                            'چون فقط در این مدل می توان plaque index را اندازه گرفت.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فصل اشاره می کند pulp ملتهب ممکن است پاسخ متفاوتی به liners، cements و restorative agents بدهد و بنابراین مدل های induced pulpitis برای ارزیابی reparative dentin توسعه می یابند. رد گزینه ها: ب نادرست است؛ healthy pulp هم در usage tests بررسی شده است. ج microscopic examination همچنان لازم است. د plaque index موضوع اصلی pulp irritation test نیست.',
                    ],
                    [
                        'question' => 'کدام رخداد تاریخی در فصل به عنوان عامل افزایش اولویت biological testing برای medical devices از جمله dental materials آمده است؟',
                        'options' => [
                            'انتشار ISO 7405 در 2008',
                            'تصویب Medical Device Bill در 1976',
                            'اضافه شدن Ames test در 1982',
                            'تعریف long-term implant success پس از 7 سال',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: پس از passage of the Medical Device Bill by Congress in 1976، biological testing برای همه medical devices از جمله dental materials اولویت بالایی یافت. رد گزینه ها: الف و ج رخدادهای استانداردی مهم اند اما عامل اشاره شده برای اولویت بخشی کلی نیستند. د تعریف طبقه بندی implant success است نه رخداد تاریخی regulatory.',
                    ],
                    [
                        'question' => 'در آزمون کلاسیک direct pulp exposure در monkey teeth که برخی restorationها با ZOE surface-sealed شدند، نتیجه 21 روزه چه برداشتی را تقویت کرد؟',
                        'options' => [
                            'surface sealing با ZOE تحریک pulp را افزایش داد.',
                            'sealed restorations تحریک pulpal کمتری داشتند و microleakage عامل مهمی دانسته شد.',
                            'همه مواد پس از sealing پاسخ inflammatory طولانی مدت یکسان داشتند.',
                            'amalgam بیشترین dentin bridging را نسبت به همه مواد ایجاد کرد.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: در مطالعه monkey teeth، sealed restorations پس از 21 روز pulpal irritation کمتری نشان دادند؛ این نتیجه نقش مهم microleakage را تقویت کرد. رد گزینه ها: الف خلاف یافته است. ج نادرست است؛ zinc phosphate response طولانی مدت داشت. د نادرست است؛ amalgam تنها ماده ای بود که bridging را به نظر می رسید جلوگیری کند.',
                    ],
                    [
                        'question' => 'کدام گزینه یک disadvantage مشترک مهم برای animal و usage tests، در مقایسه با بسیاری از in vitro tests، محسوب می شود؟',
                        'options' => [
                            'دشواری کنترل، هزینه و زمان بیشتر، و چالش های تفسیر یا ethical/legal concerns',
                            'ناتوانی کامل در نشان دادن complex systemic interactions',
                            'نبود هرگونه relevance به پاسخ in vivo',
                            'انجام فقط با isolated enzyme system',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: animal و usage tests نسبت به in vitro معمولاً expensive، time consuming، دشوار از نظر control/interpretation و همراه ethical/legal concerns هستند. رد گزینه ها: ب درباره in vitro بیشتر صدق می کند. ج نادرست است؛ animal و usage relevance بیشتری از in vitro دارند. د isolated enzyme system مربوط به in vitro است.',
                    ],
                    [
                        'question' => 'سنجش cytokine production، lymphocyte proliferation، chemotaxis یا complement activation در فصل به کدام دسته مربوط است؟',
                        'options' => [
                            'Other assays for cell function در in vitro testing',
                            'Dental implant mobility usage test',
                            'ISO supplementary tests برای biodegradation',
                            'Mucous membrane irritation test',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: این سنجه ها در بخش Other assays for cell function آمده اند و برای سنجش immune function یا tissue reactions در in vitro استفاده می شوند. رد گزینه ها: ب، ج و د به آزمون های دیگری مربوط اند و با cytokine/chemotaxis/complement assay هم خوان نیستند.',
                    ],
                    [
                        'question' => 'implantی که 5 سال در عملکرد باقی مانده است، در طبقه بندی فصل از نظر زمان بقا در کدام گروه قرار می گیرد؟',
                        'options' => [
                            'Early implant success',
                            'Intermediate implant success',
                            'Long-term success',
                            'Failed osseointegration',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: Intermediate implant success برای بقای 3 تا 7 سال تعریف شده است؛ implant با 5 سال بقا در این دسته قرار می گیرد. رد گزینه ها: الف برای 1 تا 3 سال است. ج برای بیش از 7 سال است. د از اطلاعات سوال استنباط نمی شود.',
                    ],
                    [
                        'question' => 'محدودیت اصلی مدل هرمی اولیه که باعث تکامل طرح های جدیدتر شد، کدام بود؟',
                        'options' => [
                            'ناتوانی in vitro و animal tests در غربال کردن قطعی مواد به صورت screen in یا screen out',
                            'حذف کامل clinical trials از فرایند ارزیابی',
                            'نبود هیچ نوع initial یا secondary test در استانداردها',
                            'تمرکز انحصاری مدل های جدید بر toxicity و حذف immunogenicity',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فصل می گوید ناتوانی in vitro و animal tests در screen in/out قطعی مواد باعث توسعه newer schemes شد. رد گزینه ها: ب نادرست است؛ clinical trials در مدل هرمی وجود داشتند. ج نادرست است؛ initial و secondary tests در استانداردها وجود دارند. د نادرست است؛ طرح های جدید نگاه گسترده تر و ongoing دارند.',
                    ],
                    [
                        'question' => 'در مطالعه مستقیم روی pulp monkey teeth، کدام ماده با وجود surface sealing تنها موردی بود که long-term inflammatory response ایجاد کرد؟',
                        'options' => [
                            'Amalgam',
                            'Composite',
                            'Zinc phosphate cement',
                            'Silicate cement',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: در مطالعه direct pulp exposure، فقط zinc phosphate cement long-term inflammatory response ایجاد کرد. رد گزینه ها: الف، ب و د در متن به عنوان تنها عامل long-term inflammatory response ذکر نشده اند؛ اگرچه همه مواد در 7 روز درجاتی irritation داشتند.',
                    ],
                    [
                        'question' => 'اگر ماده ای برای تماس با alveolar bone یا apical periodontal connective tissues طراحی شده باشد، کدام آزمون از نظر شباهت site می تواند ارزش بیشتری داشته باشد؟',
                        'options' => [
                            'Connective tissue implantation test',
                            'Agar overlay method بدون سد dentin',
                            'فقط colorimetric MTT در cell line',
                            'Ames test با Salmonella',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: وقتی material در تماس با alveolar bone یا apical periodontal connective tissues به کار می رود، connective tissue implantation tests به دلیل شباهت site با کاربرد، ارزش بیشتری دارند. رد گزینه ها: ب و ج فقط مدل های in vitro هستند. د mutagenesis را می سنجد، نه tissue compatibility در این sites.',
                    ],
                    [
                        'question' => 'در direct in vitro tests، کدام دو شکل تماس با cell system در فصل تفکیک شده اند؟',
                        'options' => [
                            'حضور فیزیکی خود material با سلول ها، یا تماس extract ماده با cell system',
                            'تماس در gingival crevice، یا تماس در bone marrow',
                            'clinical trial در انسان، یا usage test در سگ',
                            'implantation در muscle، یا implantation در bone',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: direct in vitro tests به حضور فیزیکی material با cells و تماس extract material با cell system تقسیم می شوند. رد گزینه ها: ب، ج و د مربوط به animal/usage یا anatomical sites هستند و تعریف direct in vitro contact نیستند.',
                    ],
                    [
                        'question' => 'چرا گفتن اینکه «این ماده biocompatible است» بدون توضیح کاربرد، از دید فصل کم معناست؟',
                        'options' => [
                            'چون biocompatibility فقط در مواد polymeric مطرح است.',
                            'چون یک ماده ممکن است برای full cast crown قابل قبول باشد ولی برای implant پاسخ زیستی لازم را ایجاد نکند.',
                            'چون مواد فقط بر اساس ISO initial tests قضاوت می شوند و کاربرد نقشی ندارد.',
                            'چون هر ماده ای که inflammation ندهد الزاماً osseointegration هم ایجاد می کند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: متن مثال می زند ماده ای که به عنوان full cast crown acceptable است ممکن است برای dental implant acceptable نباشد، چون پاسخ زیستی مورد انتظار متفاوت است. رد گزینه ها: الف نادرست است؛ biocompatibility برای همه classes مطرح است. ج نادرست است؛ application نقش اصلی دارد. د نادرست است؛ نبود inflammation معادل osseointegration نیست.',
                    ],
                    [
                        'question' => 'در mucosa and gingival usage tests، شدت پاسخ با کدام معیار بافتی دسته بندی می شود؟',
                        'options' => [
                            'تعداد mononuclear inflammatory cells در epithelium و connective tissues مجاور',
                            'مقدار dye عبوری از membrane در cell culture',
                            'میزان reversion Salmonella typhimurium',
                            'درصد gold content در alloy',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: در mucosa/gingival usage tests، پاسخ به slight، moderate یا severe بر اساس تعداد mononuclear inflammatory cells، عمدتاً lymphocytes و neutrophils، در epithelium و connective tissue مجاور طبقه بندی می شود. رد گزینه ها: ب معیار cytotoxicity membrane permeability است. ج Ames test است. د ویژگی alloy است نه پاسخ بافتی.',
                    ],
                    [
                        'question' => 'کدام جمله فلسفه فصل درباره ترکیب آزمون های biocompatibility را دقیق تر بیان می کند؟',
                        'options' => [
                            'یک آزمون استاندارد کافی است اگر ماده در آن قبول شود.',
                            'برای شناخت کامل تر، ترکیبی از in vitro، animal و usage tests لازم است، زیرا هرکدام جنبه متفاوتی از پاسخ زیستی را می سنجند.',
                            'in vitro tests به دلیل نداشتن relevance هیچ جایگاهی در توسعه مواد ندارند.',
                            'usage tests چون gold standard هستند، از نظر اخلاقی و هزینه مشکلی ندارند. نیمه دوم فصل ۶: از Dentin Bonding تا Summary',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: فلسفه فصل این است که هیچ آزمون واحدی برای characterization کامل کافی نیست و ترکیب in vitro، animal و usage tests دقیق تر و cost-effective تر است. رد گزینه ها: الف خلاف متن است. ج نادرست است؛ in vitro جایگاه دارد. د نادرست است؛ usage tests gold standard اند اما مشکلات هزینه، زمان و ethical/legal دارند.',
                    ],
                ],
            ],
            [
                'slug' => '6-2',
                'path' => '/exams/craig-dental-materials/6-10/6-2/',
                'questionCount' => 40,
                'label' => 'فصل ۶ - قسمت دوم',
                'title' => 'آزمون فصل ۶ - قسمت دوم',
                'subtitle' => '۴۰ سؤال چهارگزینه‌ای از مجموعه کریگ.',
                'description' => 'مرور ۴۰ سؤال از مجموعه کریگ.',
                'eyebrow' => 'کریگ | فصول ۶ تا ۱۰ | فصل ۶ - قسمت دوم',
                'backHref' => '/exams/craig-dental-materials/6-10/',
                'backLabel' => 'بازگشت به فصول ۶ تا ۱۰ کریگ',
                'autoAdvance' => true,
                'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'siteSubtitle' => 'آزمون‌ها',
                'siteBadge' => 'آزمون رفرنسی',
                'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'questions' => [
                    [
                        'question' => 'پس از cutting dentin، smear layer چه ویژگی اصلی دارد؟',
                        'options' => [
                            'لایه ای 1 تا 2 میکرومتری از debris آلی و معدنی است که سطح و tubules را می پوشاند و smear plugs می سازد.',
                            'لایه ای از resin-collagen است که فقط پس از etching و bonding تشکیل می شود.',
                            'سد کاملاً غیرقابل نفوذی است که عبور molecules بزرگ مانند albumin را ناممکن می کند.',
                            'لایه ای فلزی است که از corrosion products amalgam ساخته می شود.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: smear layer پس از cutting dentin به صورت لایه 1 تا 2 μm از organic/inorganic debris روی سطح و داخل tubules به شکل smear plugs باقی می ماند. رد گزینه ها: ب hybrid layer پس از bonding است. ج نادرست است؛ اگرچه convective flow را کم می کند، molecules بزرگی مانند albumin می توانند diffusion داشته باشند. د مربوط به amalgam corrosion نیست.',
                    ],
                    [
                        'question' => 'درباره bleaching agents روی vital teeth، کدام نتیجه با فصل سازگارتر است؟',
                        'options' => [
                            'peroxideها فقط از dentin عبور می کنند و از intact enamel عبور نمی کنند.',
                            'cytotoxicity آن ها تا حد زیادی به concentration peroxide وابسته است و نفوذ سریع آن ها به dentin و حتی enamel گزارش شده است.',
                            'tooth sensitivity در clinical studies گزارش نشده است.',
                            'chemical burn gingiva حتی با tray مناسب اجتناب ناپذیر است.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: bleaching agents معمولاً peroxide دارند؛ peroxideها می توانند ظرف چند دقیقه از dentin و حتی intact enamel عبور کنند و cytotoxicity عمدتاً به concentration وابسته است. رد گزینه ها: الف عبور از enamel را نادیده می گیرد. ج خلاف clinical reports درباره tooth sensitivity است. د نادرست است؛ tray مناسب gingival burn را مشکل زا نمی کند.',
                    ],
                    [
                        'question' => 'در حفره عمیق بدون liner با remaining dentin حدود 0.5 mm یا کمتر، درد پس از amalgam بیشتر با کدام سازوکار توضیح داده می شود؟',
                        'options' => [
                            'آزادسازی polyacrylic acid با وزن مولکولی بالا',
                            'رسانایی حرارتی و الکتریکی بالای amalgam و نبود سد کافی',
                            'estrogen-like response ناشی از bisphenol A',
                            'فعال شدن MMP در hybrid layer',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: در deep unlined cavities، درد amalgam با high thermal and electrical conductivity توضیح داده شده و سد dentin یا insulating material آن را کاهش می دهد. رد گزینه ها: الف به glass ionomer/polyacrylate مرتبط است. ج درباره composite controversy است. د درباره dentin adhesive hybrid layer است.',
                    ],
                    [
                        'question' => 'pulpal biocompatibility نسبتاً مطلوب glass ionomer بیشتر به کدام ویژگی نسبت داده شده است؟',
                        'options' => [
                            'اسیدی بودن شدید و نفوذ کامل polyacrylic acid از dentin',
                            'weak acidic nature و high molecular weight polyacrylic acid که مانع diffusion از dentin می شود',
                            'وجود unreacted mercury و copper در ساختار',
                            'تشکیل oxide layer در کمتر از یک ثانیه',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: biocompatibility pulp در glass ionomer به weak acidic nature و high molecular weight polyacrylic acid نسبت داده می شود؛ polyacrylic acid به دلیل اندازه بزرگ از dentin عبور نمی کند. رد گزینه ها: الف نفوذ کامل و اسیدی بودن شدید را به اشتباه مطرح می کند. ج مربوط به amalgam است. د مربوط به titanium implant است.',
                    ],
                    [
                        'question' => 'در dentin adhesive interface، آزاد شدن MMPs از dentin چه پیامدی می تواند داشته باشد؟',
                        'options' => [
                            'افزایش دوام bond به دلیل mineralization فوری collagen',
                            'enzymatic degradation of exposed collagen در hybrid layer و کاهش دوام bond',
                            'تشکیل fibrous capsule اطراف implant',
                            'مهار کامل bacterial plaque در margin',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: acid components در dentin adhesives می توانند MMPs را از dentin آزاد کنند؛ MMPها exposed collagen در hybrid layer را enzymatically degrade کرده و bond durability را کاهش می دهند. رد گزینه ها: الف خلاف اثر degradation است. ج مربوط به nonbioactive ceramics/implant response است. د در متن چنین اثر کاملی برای plaque ذکر نشده است.',
                    ],
                    [
                        'question' => 'در soft-tissue reactions اطراف restoration، کدام وضعیت احتمال inflammation را افزایش می دهد؟',
                        'options' => [
                            'surface کاملاً صاف و margin بسته در ناحیه با شست وشوی مناسب saliva',
                            'rough surface، open margin یا ناحیه ای با اثر شست وشوی کم saliva',
                            'نبود plaque و نبود released products از material',
                            'polishing کامل composite همراه با کاهش surface area',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: rough surfaces، open margins و نواحی با salivary washing کم مانند interproximal areas و deep gingival pockets plaque retention و اثر released products را افزایش می دهند و inflammation را تشدید می کنند. رد گزینه ها: الف شرایط کم خطرتر است. ج عامل ایجاد التهاب را حذف می کند. د polishing معمولاً cytotoxicity و roughness-related plaque را کاهش می دهد.',
                    ],
                    [
                        'question' => 'پاسخ اولیه pulp به calcium hydroxide aqueous pulp-capping agent با pH بسیار alkaline معمولاً چگونه توصیف شده است؟',
                        'options' => [
                            'necrosis سطحی تا عمق 1 mm یا بیشتر، سپس infiltration neutrophils و بعداً dentin bridge formation',
                            'عدم necrosis و تشکیل فوری bond epithelium',
                            'فقط hypersensitivity سیستمیک بدون واکنش موضعی',
                            'cytotoxicity صفر به علت high pH',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: calcium hydroxide با pH>12 در screening tests بسیار cytotoxic است؛ در pulp ابتدا necrosis تا 1 mm یا بیشتر ایجاد می شود، سپس neutrophils وارد subnecrotic zone می شوند و در هفته ها تا ماه ها calcification و dentin bridge رخ می دهد. رد گزینه ها: ب مربوط به epithelium implant نیست. ج پاسخ موضعی اصلی است نه hypersensitivity سیستمیک. د high pH علت cytotoxicity شدید است، نه نبود آن.',
                    ],
                    [
                        'question' => 'چرا titanium در implantology از نظر corrosion resistance مناسب است؟',
                        'options' => [
                            'چون هیچ oxide layer روی سطح آن تشکیل نمی شود.',
                            'چون در کمتر از یک ثانیه لایه نازک و conformal از titanium oxides تشکیل می دهد که corrosion resistant است.',
                            'چون ion release از titanium اساساً غیرممکن است.',
                            'چون با connective tissue پیوند شیمیایی قوی تری از bone ایجاد می کند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: titanium پس از cast شدن، در کمتر از یک ثانیه لایه conformal نازکی از titanium oxides می سازد که corrosion resistant است و osseointegration را ممکن می کند. رد گزینه ها: الف خلاف متن است. ج نادرست است؛ titanium ion release رخ می دهد. د connective tissue به titanium bond نمی شود، هرچند seal محکمی می سازد.',
                    ],
                    [
                        'question' => 'در مورد resin-based restorative materials، کدام گزاره دقیق تر است؟',
                        'options' => [
                            'تازه set شده ها در in vitro اغلب moderate cytotoxicity طی 24 تا 72 ساعت ایجاد می کنند و این اثر با گذشت زمان یا dentin barrier کاهش می یابد.',
                            'light-cured resins همیشه شدیدتر از chemically cured systems cytotoxic هستند.',
                            'liner یا bonding agent پاسخ pulp را نسبت به resin composite تشدید می کند.',
                            'direct placement روی pulp طبق فصل اثرات long-term شناخته شده و مطلوب دارد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: freshly set chemically cured و light-cured resins در in vitro اغلب طی 24 تا 72 ساعت moderate cytotoxicity دارند؛ cytotoxicity پس از 24 تا 48 ساعت و با dentin barrier کاهش می یابد. رد گزینه ها: ب نادرست است؛ light-cured معمولاً less cytotoxic گزارش شده اما وابسته به curing efficiency و resin system است. ج خلاف متن است؛ liner/bonding agent پاسخ pulp را minimal می کند. د long-term effects direct pulp placement مشخص نیست و less favorable suspected است.',
                    ],
                    [
                        'question' => 'آسیب pulpal اولیه zinc phosphate cement در deep cavities بیشتر با کدام عامل ها توضیح داده شده است؟',
                        'options' => [
                            'pH اولیه پایین و leaching zinc ions',
                            'آزادسازی fluoride از glass powder',
                            'formation of passive titanium oxide',
                            'release of eugenol با اثر antiinflammatory در غلظت پایین',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: zinc phosphate cement در in vitro strong-to-moderate cytotoxicity دارد که با leaching zinc ions و low pH توضیح داده شده؛ در deep cavities pH اولیه پایین، damage ایجاد می کند. رد گزینه ها: ب مربوط به glass ionomer است. ج مربوط به titanium است. د اثر ZOE/eugenol در غلظت پایین است.',
                    ],
                    [
                        'question' => 'چرا polished resin composites در in vitro معمولاً cytotoxicity کمتری از نمونه های unpolished نشان می دهند؟',
                        'options' => [
                            'چون polishing مقدار unpolymerized components در air-inhibited layer قابل leach شدن را کاهش می دهد.',
                            'چون polishing باعث افزایش شدید surface roughness و leaching می شود.',
                            'چون polishing bisphenol A را به titanium oxide تبدیل می کند.',
                            'چون unpolished samples هیچ تماس مستقیمی با fibroblasts ندارند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: initial cytotoxicity composites در تماس با fibroblasts عمدتاً از unpolymerized components در air-inhibited layer ناشی می شود؛ polishing این layer و leachable components را کاهش می دهد. رد گزینه ها: ب polishing roughness را به عنوان عامل التهاب افزایش نمی دهد. ج بی معنا و خارج از منبع است. د unpolished samples هم می توانند contact مستقیم داشته باشند.',
                    ],
                    [
                        'question' => 'در casting alloys، کدام الگوی hypersensitivity مطابق فصل است؟',
                        'options' => [
                            'nickel allergy نادرتر از palladium allergy است.',
                            'palladium allergy تقریباً سه برابر nickel allergy رخ می دهد.',
                            'nickel allergy در 10 تا 20 درصد females گزارش شده و true palladium allergy حدود یک سوم nickel allergy است.',
                            'هر بیمار با nickel allergy الزاماً به palladium هم allergic است.',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: nickel allergy در 10% تا 20% females رخ می دهد و true palladium allergy حدود یک سوم nickel allergy گزارش شده است. رد گزینه ها: الف و ب نسبت ها را برعکس می کنند. د نادرست است؛ بیماران با palladium allergy تقریباً همیشه به nickel allergic هستند، اما converse درست نیست.',
                    ],
                    [
                        'question' => 'درباره MTA و calcium hydroxide در dentin bridge formation، کدام گزینه با متن همخوان تر است؟',
                        'options' => [
                            'اثر آن ها فقط واکنش ساده به pH بالا است و هیچ مولکول dentin-derived دخیل نیست.',
                            'ممکن است با solubilization مولکول های bioactive مانند TGF-β1 و جذب سلول های undifferentiated فرایند repair را تحریک کنند.',
                            'MTA هیچ ارتباطی با calcium hydroxide ندارد.',
                            'pulp cells در برابر MTA neither proliferate nor migrate.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: فصل بیان می کند calcium hydroxide و MTA ممکن است bioactive molecules از dentin، مانند TGF-β1، را solubilize کنند و با recruiting undifferentiated cells و differentiation به odontoblast-like cells dentin bridge formation را تحریک کنند. رد گزینه ها: الف بیش از حد ساده سازی شده و متن آن را پیچیده تر می داند. ج نادرست است؛ main soluble component from MTA، calcium hydroxide گزارش شده است. د خلاف شواهد proliferation/migration pulp cells است.',
                    ],
                    [
                        'question' => 'resorbable sutures اولیه از نظر ترکیب و مسیر breakdown چگونه معرفی شده اند؟',
                        'options' => [
                            'copolymer of PLA/PGA که در بدن hydrolytic decomposition به CO2 و H2O می دهد.',
                            'titanium-aluminum-vanadium alloy که passive oxide می سازد.',
                            'polyacrylic acid با high molecular weight که از dentin عبور نمی کند.',
                            'ceramic nonbioactive که fibrous encapsulation ایجاد می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: resorbable sutures اولیه از copolymer polylactic acid و polyglycolic acid ساخته شدند و در بدن به CO2 و H2O hydrolytic decomposition می یابند. رد گزینه ها: ب titanium alloy implant است. ج polyacrylic acid cement/glass ionomer است. د nonbioactive ceramics را توصیف می کند.',
                    ],
                    [
                        'question' => 'در مقایسه HEMA و Bis-GMA، کدام نکته در فصل آمده است؟',
                        'options' => [
                            'HEMA در tissue culture حداقل 100 برابر cytotoxic تر از Bis-GMA است.',
                            'HEMA حداقل 100 برابر less cytotoxic از Bis-GMA است، اما در dentin بسیار نازک می تواند in vivo cytotoxic باشد.',
                            'HEMA قادر به diffusion از dentin نیست.',
                            'HEMA فقط در chemically cured resin composites یافت می شود.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: HEMA حداقل 100 برابر less cytotoxic از Bis-GMA در tissue culture معرفی شده، اما می تواند از dentin diffuse کند و اگر dentin floor بسیار نازک باشد (<0.1 mm)، evidence برای in vivo cytotoxicity وجود دارد. رد گزینه ها: الف نسبت cytotoxicity را برعکس می کند. ج خلاف diffusion گزارش شده است. د در bonding systems مطرح شده و محدود به chemically cured resin composite نیست.',
                    ],
                    [
                        'question' => 'درباره soft denture liners، کدام عامل نگرانی biocompatibility را بهتر توضیح می دهد؟',
                        'options' => [
                            'آزادسازی plasticizers که در in vitro و animal tests می تواند cytotoxicity و epithelial changes ایجاد کند.',
                            'تشکیل فوری osseointegration در gingiva',
                            'نبود هرگونه تماس نزدیک با mucosa',
                            'کاهش pH به دلیل تجزیه به CO2 و H2O',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: soft denture liners ممکن است plasticizers آزاد کنند؛ cell culture tests cytotoxicity شدید و animal tests epithelial changes مرتبط با plasticizers را نشان داده اند. رد گزینه ها: ب مربوط به implant bone است. ج این مواد در تماس نزدیک با gingiva/mucosa هستند. د به resorbable PLA/PGA degradation مربوط تر است.',
                    ],
                    [
                        'question' => 'چرا varnishهای نازک مانند resin-based copal varnishes معمولاً زیر resin-based materials به کار نمی روند؟',
                        'options' => [
                            'چون resin components می توانند فیلم نازک varnish را dissolve کنند.',
                            'چون ضخامت آن ها برای thermal insulation بسیار زیاد است.',
                            'چون حتماً dentin bridge را سریع تر از calcium hydroxide می سازند.',
                            'چون با zinc ion chelation cytotoxicity را افزایش می دهند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: thin liners مثل resin-based copal varnishes زیر resin-based materials معمولاً استفاده نمی شوند، زیرا resin components فیلم نازک varnish را dissolve می کنند. رد گزینه ها: ب نادرست است؛ به دلیل thin layer، thermal insulation فراهم نمی کنند. ج نقش اصلی و قطعی آن ها نیست. د به zinc polyacrylate/EDTA مربوط است.',
                    ],
                    [
                        'question' => 'ceramic implant materials در متن چگونه از نظر واکنش زیستی عمومی توصیف شده اند؟',
                        'options' => [
                            'اغلب بسیار toxic هستند چون در حالت reduced قرار دارند.',
                            'به دلیل oxidized state و corrosion resistance، معمولاً toxic effects بسیار کم، nonimmunogenic و noncarcinogenic دارند.',
                            'همیشه bioactive هستند و هرگز fibrous encapsulation ایجاد نمی کنند.',
                            'از نظر biocompatibility ضعیف تر از همه metals معرفی شده اند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: ceramic implant materials چون oxidized و highly corrosion resistant هستند، toxic effects بسیار کم دارند و به عنوان nonimmunogenic و noncarcinogenic توصیف شده اند. رد گزینه ها: الف خلاف متن است. ج نادرست است؛ nonbioactive ceramics ممکن است fibrous encapsulation ایجاد کنند. د متن چنین مقایسه کلی ای مطرح نمی کند.',
                    ],
                    [
                        'question' => 'در acid etching dentin، کدام شرط اثر تحریک کننده acid بر pulp را کمتر محتمل می کند؟',
                        'options' => [
                            'remaining dentin حدود 0.5 mm یا بیشتر، به دلیل buffering موثر protons توسط dentin',
                            'حذف کامل dentin barrier و ضخامت کمتر از 0.1 mm',
                            'استفاده از acids ضعیف citric یا lactic که بهتر از بقیه buffered می شوند',
                            'رسیدن acid به pulp در تمام موارد etching',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: dentin buffer موثر protons است و 0.5 mm dentin thickness برای محافظت در برابر بیشتر acidها adequate گزارش شده است. رد گزینه ها: ب ریسک را افزایش می دهد. ج نادرست است؛ citric/lactic acids به خوبی buffered نمی شوند چون weak acids efficiently dissociate نمی کنند. د penetration معمولاً کمتر از 100 μm است و رسیدن به pulp در همه موارد مطرح نیست.',
                    ],
                    [
                        'question' => 'چرا ZOE در usage tests با class 5 cavities نسبتاً innocuous گزارش شده، با وجود اثر مستقیم eugenol در in vitro؟',
                        'options' => [
                            'چون diffusion through dentin eugenol را چندین مرتبه رقیق می کند و غلظت پایین تر می تواند nerve transmission و inflammatory mediators را کاهش دهد.',
                            'چون eugenol در تماس مستقیم با سلول ها هیچ اثری ندارد.',
                            'چون ZOE هیچ گاه temporary seal ایجاد نمی کند.',
                            'چون pulpal side همیشه غلظت bactericidal 10−2 M دارد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: eugenol در direct contact سلول را fix می کند و respiration/nerve transmission را کاهش می دهد، اما diffusion از dentin غلظت را از حدود bactericidal زیر ZOE به مقدارهای بسیار پایین تر در pulpal side می رساند که می تواند antiinflammatory/analgesic باشد؛ ZOE نیز temporary seal ایجاد می کند. رد گزینه ها: ب خلاف متن است. ج خلاف متن است. د pulpal side با 10−4 M یا کمتر توصیف شده، نه 10−2 M.',
                    ],
                    [
                        'question' => 'در مطالعه in vitro اثر عناصر و فازهای amalgam، کدام برداشت با فصل سازگارتر است؟',
                        'options' => [
                            'pure tin از pure copper و zinc cytotoxicتر است.',
                            'major contributor به cytotoxicity amalgam alloy powders احتمالاً copper و برای amalgam احتمالاً zinc است.',
                            'اضافه کردن selenium cytotoxicity amalgam را کاهش قطعی می دهد.',
                            'حضور zinc سطح cytotoxicity را پایین می آورد.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: نتایج in vitro نشان دادند copper و zinc cytotoxicity بیشتری دارند؛ جمع بندی متن این است که در amalgam alloy powders عامل عمده احتمالاً copper و در amalgam احتمالاً zinc است. رد گزینه ها: الف خلاف متن است؛ tin cytotoxic نشان داده نشده است. ج نادرست است؛ selenium cytotoxicity را کاهش نداد و اضافه کردن زیاد آن افزایش داد. د خلاف متن است؛ هرگاه zinc present بود cytotoxicity بالاتر بود.',
                    ],
                    [
                        'question' => 'درباره کاربرد مستقیم glass ionomer روی living pulp، کدام نتیجه مناسب تر است؟',
                        'options' => [
                            'به عنوان direct pulp-capping agent به خوبی tolerated نشان داده شده است.',
                            'برای تماس مستقیم با living pulp به خوبی tolerated نشان داده نشده است.',
                            'همیشه بدون initial inflammation dentin bridge یکنواخت ایجاد می کند.',
                            'چون fluoride release دارد، cytotoxicity in vitro ندارد.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: glass ionomer در usage tests پاسخ pulp خفیف دارد، اما when placed directly upon living pulp tissue به عنوان direct pulp-capping agent well tolerated نشان داده نشده است. رد گزینه ها: الف خلاف متن است. ج پاسخ مستقیم و یکنواخت تضمین نشده است. د نادرست است؛ fluoride release در in vitro به cytotoxicity نسبت داده شده است.',
                    ],
                    [
                        'question' => 'در usage tests resin composites، پاسخ pulp با حدود 0.5 mm remaining dentin چگونه گزارش شده است؟',
                        'options' => [
                            'low to moderate بعد از 3 روز، کاهش با افزایش دوره postoperative تا 5-8 هفته و همراهی با reparative dentin',
                            'severe irreversible necrosis در همه موارد تا 8 هفته',
                            'نبود هرگونه واکنش در 3 روز، اما افزایش شدید پس از 8 هفته',
                            'واکنش فقط در حضور amalgam phases ایجاد می شود.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: resin composites در cavities با حدود 0.5 mm remaining dentin، پس از 3 روز low-to-moderate inflammation ایجاد کردند که در 5-8 هفته کاهش یافت و با reparative dentin همراه شد. رد گزینه ها: ب شدت و برگشت ناپذیری را بیش از متن بیان می کند. ج روند زمانی را برعکس می کند. د به amalgam phases مربوط نیست.',
                    ],
                    [
                        'question' => 'در مقایسه finished و unfinished restorations که تا gingival crevice امتداد یافته اند، کدام نتیجه گزارش شده است؟',
                        'options' => [
                            'unfinished materials پاسخ inflammatory بسیار خفیف تری می دهند.',
                            'finished materials پاسخ inflammatory ملایم تری نسبت به unfinished materials می دهند.',
                            'surface roughness فقط در حضور implant titanium اهمیت دارد.',
                            'plaque retention با roughness کاهش می یابد.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: usage tests نشان داده اند finished materials هنگامی که تا gingival crevice امتداد دارند، inflammatory response بسیار ملایم تری از unfinished materials ایجاد می کنند. رد گزینه ها: الف برعکس متن است. ج surface roughness برای restorations و alloys نیز مهم است. د roughness plaque retention را افزایش می دهد.',
                    ],
                    [
                        'question' => 'denture base materials، به ویژه methacrylates، بیشتر از دیدگاه hypersensitivity برای کدام گروه exposure بالاتری ایجاد می کنند؟',
                        'options' => [
                            'dental و laboratory personnel که مکرراً با unreacted components تماس دارند.',
                            'بیمارانی که فقط polymerized denture را استفاده می کنند.',
                            'افرادی که هیچ تماس با monomer ندارند.',
                            'فقط بیماران دارای titanium implants',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: بیشترین potential برای hypersensitization در denture base materials مربوط به dental/laboratory personnel است که مکرراً با unreacted components تماس دارند. رد گزینه ها: ب بیماران اغلب polymerized materials دریافت می کنند و incidence پایین تر است. ج exposure ندارد. د ارتباط اختصاصی با titanium implant ندارد.',
                    ],
                    [
                        'question' => 'کدام مزیت Ti-6Al-4V نسبت به commercially pure titanium در فصل ذکر شده است؟',
                        'options' => [
                            'استحکام و fatigue resistance بیشتر همراه با stiffness و thermal properties مطلوب مشابه CP titanium',
                            'corrosion rate بیشتر برای تحریک osseointegration',
                            'ناتوانی در آزادسازی هرگونه ion',
                            'حذف کامل نگرانی درباره aluminum و vanadium release',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: Ti-6Al-4V نسبت به CP titanium significantly stronger است و fatigue resistance بهتری دارد، در حالی که desirable stiffness و thermal properties مشابه دارد. رد گزینه ها: ب نادرست است؛ corrosion resistance مطلوب است، نه corrosion بیشتر. ج نادرست است؛ release رخ می دهد. د متن می گوید درباره aluminum و vanadium questions remain.',
                    ],
                    [
                        'question' => 'یکی از برداشت های جدیدتر درباره glass ionomer روی dentin کدام است؟',
                        'options' => [
                            'mild demineralization ممکن است bioactive molecules را از dentin آزاد کند و به repair کمک کند.',
                            'polyacrylic acid همیشه تا pulp نفوذ می کند و necrosis 1 mm ایجاد می کند.',
                            'واکنش repair فقط در نبود هرگونه initial inflammation رخ می دهد.',
                            'glass ionomer برخلاف dentin adhesives هیچ اثر اسیدی بر dentin ندارد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فصل احتمال می دهد mild demineralization ناشی از glass ionomer bioactive molecules را از dentin آزاد کند و repair process/reactionary dentin را یاری دهد. رد گزینه ها: ب نفوذ polyacrylic acid و necrosis را به اشتباه بیان می کند. ج initial inflammatory reaction می تواند قبل از repair رخ دهد. د glass ionomer acidic dental material محسوب می شود.',
                    ],
                    [
                        'question' => 'در zinc polyacrylate cements، چرا برخی پژوهشگران cytotoxicity در tissue culture را artifact احتمالی می دانند؟',
                        'options' => [
                            'phosphate buffers در culture medium می توانند zinc ion leaching از cement را تسهیل کنند و EDTA با chelation zinc inhibition را معکوس کند.',
                            'این cementها هرگز zinc یا fluoride آزاد نمی کنند.',
                            'subcutaneous و bone implant tests نشان داده اند که acute cytotoxicity همیشه شدید است.',
                            'polyacrylic acid در هر غلظتی در tissue culture کاملاً بی اثر است.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: برخی cytotoxicity zinc polyacrylate در tissue culture را artifact می دانند، چون phosphate buffers می توانند zinc leaching را افزایش دهند و EDTA با chelating zinc inhibition را reverse می کند. رد گزینه ها: ب خلاف متن است؛ zinc و fluoride release مطرح اند. ج long-term implant tests cytotoxicity طولانی مدت نشان نداده اند. د polyacrylic acid بالاتر از 1% در tissue culture cytotoxic گزارش شده است.',
                    ],
                    [
                        'question' => 'بر اساس summary فصل، کدام عامل می تواند biocompatibility ماده را با تغییر interaction ماده و بدن عوض کند؟',
                        'options' => [
                            'پاسخ ماده به pH، force یا biological fluids و surface features مثل roughness',
                            'فقط نام تجاری ماده',
                            'فقط رنگ ظاهری restoration بدون ارتباط با محیط',
                            'فقط میزان polishing بدون نقش plaque یا cell attachment',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: Summary بیان می کند response ماده به pH، force و biological fluids و نیز surface features مثل roughness می تواند biocompatibility را تغییر دهد و بر plaque retention، bone integration یا dentin adhesion اثر بگذارد. رد گزینه ها: ب و ج در متن مبنای biocompatibility نیستند. د polishing تنها یکی از عوامل سطحی است و plaque/cell attachment نادیده گرفته شده است.',
                    ],
                    [
                        'question' => 'اگر بیمار پس از استفاده طولانی از home bleaching دچار tooth sensitivity شود، کدام نکته از فصل برای احتیاط زیستی مهم تر است؟',
                        'options' => [
                            'peroxides می توانند در چند دقیقه از dentin و حتی intact enamel عبور کنند و نگرانی درباره long-term use روی vital teeth وجود دارد.',
                            'peroxides فقط زمانی cytotoxic هستند که با amalgam تماس یابند.',
                            'حساسیت دندان در clinical studies با bleaching رایج گزارش نشده است.',
                            'concentration peroxide نقشی در cytotoxicity ندارد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: متن می گوید peroxideها در چند دقیقه از dentin و حتی intact enamel می گذرند، cytotoxicity concentration-dependent است و درباره long-term use روی vital teeth نگرانی legitimate وجود دارد؛ tooth sensitivity نیز شایع گزارش شده است. رد گزینه ها: ب محدودیت نادرست است. ج خلاف گزارش بالینی است. د خلاف متن است.',
                    ],
                    [
                        'question' => 'در cast alloys زیر crown، کدام گزاره درباره محل اصلی تماس ionهای آزادشده دقیق تر است؟',
                        'options' => [
                            'ionهای آزادشده عمدتاً pulp را مستقیماً درگیر می کنند و gingiva نقشی ندارد.',
                            'ionهای فلزی آزادشده بیشتر با gingival و mucosal tissues تماس دارند؛ pulp بیشتر از cement نگهدارنده restoration اثر می پذیرد.',
                            'cast alloyها فاقد noble و nonnoble metals هستند.',
                            'gold content در این alloys همیشه 85 wt% ثابت است.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: در cast alloys، metal ions آزادشده به احتمال بیشتر با gingival و mucosal tissues تماس می یابند، در حالی که pulp بیشتر تحت تاثیر cement retaining restoration قرار می گیرد. رد گزینه ها: الف محل تماس را نادرست بیان می کند. ج cast alloys شامل noble و nonnoble metals هستند. د gold content بین 0 تا 85 wt% متغیر است.',
                    ],
                    [
                        'question' => 'تفاوت resin-containing calcium hydroxide compounds با aqueous calcium hydroxide suspension در پاسخ pulp چیست؟',
                        'options' => [
                            'resin-containing types کمتر irritant هستند و بدون zone of necrosis می توانند سریع تر dentin bridge formation را تحریک کنند.',
                            'resin-containing types همیشه necrosis عمیق تر از 1 mm ایجاد می کنند.',
                            'aqueous suspension هیچ cytotoxicity در screening tests ندارد.',
                            'هر دو ماده هیچ نقشی در dentin bridge formation ندارند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: resin-containing calcium hydroxide compounds کمتر irritating هستند، بدون zone of necrosis dentin bridge را سریع تر تحریک می کنند و reparative dentin adjacent to liner شکل می گیرد. رد گزینه ها: ب برعکس متن است. ج aqueous suspension به دلیل pH بالا در screening tests شدیداً cytotoxic است. د هر دو می توانند با dentin bridge formation مرتبط باشند.',
                    ],
                    [
                        'question' => 'چرا کاربرد chlorhexidine پس از dentin adhesive procedure مطرح شده است؟',
                        'options' => [
                            'برای inhibiting MMPs و کاهش degradation exposed collagen در hybrid layer',
                            'برای افزایش release mercury از amalgam margins',
                            'برای تبدیل HEMA به Bis-GMA',
                            'برای ایجاد fibrous capsule اطراف titanium implant',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: chlorhexidine به عنوان MMP inhibitor می تواند degradation of exposed collagen within the hybrid layer را کاهش دهد و برای حفظ clinical durability dentin bond توصیه شده است؛ هرچند اثر کلی آن بر pulp cells هنوز روشن نیست. رد گزینه ها: ب، ج و د هیچ کدام نقش chlorhexidine در متن نیستند.',
                    ],
                    [
                        'question' => 'طبق فصل، کاهش جهانی استفاده از amalgam عمدتاً به کدام دلیل نسبت داده شده است؟',
                        'options' => [
                            'اثبات قطعی اثرات سیستمیک نامطلوب amalgam درست استفاده شده در مطالعات علمی با کیفیت بالا',
                            'environmental concerns درباره mercury contamination در air، water و soil',
                            'ناتوانی amalgam در seal شدن طولانی مدت از طریق corrosion products',
                            'آلرژی بسیار شایع و اجتناب ناپذیر به amalgam در همه بیماران',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: متن می گوید با وجود نبود شواهد قطعی از ill effects amalgam درست placed/used، global phase-down به دلیل environmental concerns درباره mercury contamination در air، water و soil رخ داده است. رد گزینه ها: الف خلاف متن است. ج نادرست است؛ long-term sealing از طریق corrosion products رخ می دهد. د allergy به amalgam rare گزارش شده است.',
                    ],
                    [
                        'question' => 'درباره نگرانی xenoestrogenic effects در resin composites، کدام بیان دقیق تر است؟',
                        'options' => [
                            'شواهد in vivo از commercial resinها این نگرانی را اثبات کرده است.',
                            'controversy عمدتاً درباره bisphenol A و bisphenol A dimethacrylate در in vitro است، اما شواهدی برای نگرانی in vivo از commercial resin وجود ندارد.',
                            'این نگرانی فقط درباره titanium alloys مطرح است.',
                            'هیچ ارتباطی با components of composites ندارد.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: controversy درباره bisphenol A و bisphenol A dimethacrylate و estrogen-like responses در in vitro وجود دارد، اما متن می گوید evidence برای xenoestrogenic concern in vivo از commercial resin وجود ندارد. رد گزینه ها: الف خلاف متن است. ج مربوط به resin composites است نه titanium. د components composites در اصل بحث نقش دارند.',
                    ],
                    [
                        'question' => 'چرا در cavities عمیق زیر amalgam، liner می تواند علاوه بر insulation، از discoloration هم بکاهد؟',
                        'options' => [
                            'چون diffusion of released metallic elements into tooth structure را می تواند کاهش دهد.',
                            'چون باعث افزایش marginal gap و percolation می شود.',
                            'چون corrosion products را از تشکیل long-term seal منع می کند.',
                            'چون Streptococcus mutans را با غلظت بالای fluoride حذف می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: در amalgam، liner می تواند diffusion of released metallic elements into tooth structure را کاهش دهد و در نتیجه discoloration را minimize کند؛ علاوه بر این، insulation حرارتی/الکتریکی فراهم می کند. رد گزینه ها: ب و ج خلاف نقش محافظتی liner هستند. د به glass ionomer/fluoride مربوط است نه liner زیر amalgam.',
                    ],
                    [
                        'question' => 'درباره soft-tissue interface اطراف titanium implant، کدام جمله با متن سازگارتر است؟',
                        'options' => [
                            'connective tissue به titanium bond می شود و epithelium نقشی در sealing ندارد.',
                            'epithelium interface مشابه دندان تشکیل می دهد و connective tissue به titanium bond نمی شود، اما seal محکمی می سازد که ingress bacteria را محدود می کند.',
                            'periimplantitis هیچ ارتباطی با bacteria مشابه periodontitis ندارد.',
                            'down-growth epithelium به حفظ bone height کمک می کند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: epithelium با titanium interface شبیه دندان تشکیل می دهد؛ connective tissue به titanium bond نمی شود، اما tight seal ایجاد می کند که ingress bacteria و bacterial products را محدود می سازد. رد گزینه ها: الف bond connective tissue را نادرست می گوید. ج نادرست است؛ periimplantitis با بسیاری از همان bacteriaهای periodontitis همراه است. د down-growth epithelium و bone loss برای implant failure نگران کننده اند.',
                    ],
                    [
                        'question' => 'چرا polyacrylic acid در glass ionomer از نظر pulp کمتر نگران کننده است؟',
                        'options' => [
                            'وزن مولکولی بالا دارد و به علت اندازه بزرگ نمی تواند از dentin diffuse کند.',
                            'pH آن از calcium hydroxide بالاتر است.',
                            'حتماً در pulpal side به غلظت 10−2 M می رسد.',
                            'فقط در titanium alloys وجود دارد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: polyacrylic acid در glass ionomer به دلیل high molecular weight نمی تواند از dentin diffuse کند و همین به overall pulpal biocompatibility کمک می کند. رد گزینه ها: ب pH آن با calcium hydroxide قابل مقایسه نیست. ج مربوط به eugenol زیر ZOE است. د polyacrylic acid در titanium alloys وجود ندارد.',
                    ],
                    [
                        'question' => 'کدام مثال summary مفهوم location-dependent biocompatibility را بهتر نشان می دهد؟',
                        'options' => [
                            'ماده ای که روی oral mucosa قابل قبول است ممکن است اگر زیر mucosa implanted شود واکنش نامطلوب ایجاد کند.',
                            'ماده ای که در تماس مستقیم با pulp toxic است الزاماً روی dentin هم همان شدت اثر را دارد.',
                            'همه materials در هر محل oral cavity پاسخ یکسانی دارند.',
                            'surface roughness فقط بر appearance اثر دارد و بر bacterial attachment بی اثر است.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: Summary تاکید می کند location ماده تعیین کننده است؛ ماده ای که در تماس با oral mucosal surface biocompatible است ممکن است اگر beneath it implanted شود adverse reaction ایجاد کند. رد گزینه ها: ب خلاف متن است؛ dentin/enamel می توانند اثر toxic direct pulp را کاهش دهند. ج وابستگی به location را نفی می کند. د surface roughness بر bacteria، host cells و biological molecules اثر دارد.',
                    ],
                    [
                        'question' => 'گذار از «پاسخ tolerated» به «bioactive materials» در بخش resorbable materials به چه معناست؟',
                        'options' => [
                            'هدف فقط باقی ماندن دایمی ماده بدون interaction با tissue است.',
                            'هدف تولید موادی است که پاسخ کنترل شده ایجاد کنند و با breakdown/resorption مناسب، بافت بازسازی شده جایگزین ماده شود.',
                            'هدف ایجاد fibrous encapsulation در همه implantها است.',
                            'هدف حذف کامل پاسخ فیزیولوژیک به material است.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: بخش resorbable materials توضیح می دهد تمرکز از tissue response صرفاً tolerated به bioactive materials منتقل شده است؛ این مواد controlled action/reaction و breakdown/resorption مناسب دارند تا regenerated tissues جایگزین ماده شوند. رد گزینه ها: الف با resorbable/bioactive concept تضاد دارد. ج هدف مطلوب نیست و در nonbioactive ceramics دیده می شود. د متن به physiological response کنترل شده، نه حذف آن، اشاره دارد.',
                    ],
                ],
            ],
            [
                'slug' => '7-1',
                'path' => '/exams/craig-dental-materials/6-10/7-1/',
                'questionCount' => 40,
                'label' => 'فصل ۷ - قسمت اول',
                'title' => 'آزمون فصل ۷ - قسمت اول',
                'subtitle' => '۴۰ سؤال چهارگزینه‌ای از مجموعه کریگ.',
                'description' => 'مرور ۴۰ سؤال از مجموعه کریگ.',
                'eyebrow' => 'کریگ | فصول ۶ تا ۱۰ | فصل ۷ - قسمت اول',
                'backHref' => '/exams/craig-dental-materials/6-10/',
                'backLabel' => 'بازگشت به فصول ۶ تا ۱۰ کریگ',
                'autoAdvance' => true,
                'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'siteSubtitle' => 'آزمون‌ها',
                'siteBadge' => 'آزمون رفرنسی',
                'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'questions' => [
                    [
                        'question' => 'در یک درمان که انتخاب ماده باید هم با طراحی ترمیم و هم با پیامد بیمار هماهنگ شود، کدام ترکیب از معیارها طبق فصل ۷ بیشترین نقش را در تعیین «کلاس ماده» دارد؟',
                        'options' => [
                            'رنگ پذیری، قابلیت قالب گیری، مقدار رطوبت دهان، نوع سمان',
                            'esthetics، hardness، stiffness، bioactivity',
                            'روش پرداخت، زمان کار، ضخامت فیلم، نسبت پودر به مایع',
                            'هدایت حرارتی، خزش، جذب آب، قابلیت اچ شدن',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: متن انتخاب کلاس ماده را به requirements مانند esthetics، hardness، stiffness و bioactivity وابسته می داند و تاکید می کند طراحی ترمیم و کلاس ماده باید هماهنگ باشند. رد گزینه ها: الف ترکیبی از عوامل پراکنده است و به صورت معیار تعیین کلاس در فصل نیامده است. ج بیشتر به manipulation و cementation مربوط است، نه معیار اصلی کلاس. د چند خاصیت مهم دارد، اما مجموعه ای نیست که فصل برای تعیین کلاس ماده فهرست کرده باشد.',
                    ],
                    [
                        'question' => 'برای ساخت چهارچوب یک removable partial denture framework، چرا انتخاب ماده معمولاً به یک کلاس محدود می شود؟',
                        'options' => [
                            'زیرا این کاربرد به رنگ طبیعی و ترنسلوسنسی بالا وابسته است.',
                            'زیرا پلیمرها در محیط آبی بیشترین پایداری بلندمدت را دارند.',
                            'زیرا نیاز طراحی با stiffness و strength فلزات هماهنگ تر است.',
                            'زیرا ceramicها در برابر بارهای تکراری تغییر شکل پلاستیک کافی دارند.',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: فصل برای removable partial denture frameworks نمونه ای می آورد که در آن یک کلاس مناسب تر است؛ با توجه به متن، این به ویژگی هایی مانند strength و stiffness فلزات مرتبط است. رد گزینه ها: الف بر esthetics تمرکز دارد، در حالی که framework چنین نیاز اصلی ندارد. ب برخلاف متن است؛ polymers کمترین پایداری بلندمدت در محیط آبی را دارند. د ceramics رفتار پلاستیک اندک و brittleness دارند، پس برای چنین frameworkی رقیب مناسب نیستند.',
                    ],
                    [
                        'question' => 'ماده ای در آزمایشگاه دندان پزشکی به سیم کشیده می شود و پس از شکل دهی، سطحی براق و مقاوم به شکست نشان می دهد. این توصیف بیشترین انطباق را با کدام کلاس دارد؟',
                        'options' => [
                            'Metal/alloy',
                            'Polymer',
                            'Ceramic',
                            'Resin composite',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فلزات می توانند به wire کشیده شوند، machined یا cast شوند، polishable luster دارند و fracture resistance خوبی نشان می دهند. رد گزینه ها: ب polymers به wire کشیده شدن و luster فلزی به عنوان ویژگی شاخص ندارند. ج ceramics brittle هستند و ductility ندارند. د composites معمولاً polymer matrix و ceramic particles دارند و توصیف wire drawing با آن ها سازگار نیست.',
                    ],
                    [
                        'question' => 'در تحلیل رفتار فلزات خالص، کدام عامل مستقیماً منشا electrical conductivity معرفی شده است؟',
                        'options' => [
                            'آرایش تصادفی grainها در آلیاژ دندانی',
                            'حرکت آزادتر valence electrons در lattice',
                            'حضور metalloids در جدول تناوبی',
                            'افزایش solvation energy یون آزادشده',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: فصل electrical conductivity فلزات را ناشی از mobility of valence electrons در lattice معرفی می کند. رد گزینه ها: الف grain orientation می تواند جهت مندی را میانگین گیری کند، نه منشا conductivity باشد. ج metalloids اجزای مهم برخی آلیاژها هستند، اما علت مستقیم conductivity فلزات در متن نیستند. د solvation energy به corrosion مربوط است، نه هدایت الکتریکی.',
                    ],
                    [
                        'question' => 'در واحد سلولی BCC، کدام توصیف با ساختار متن فصل هماهنگ تر است؟',
                        'options' => [
                            'اتم ها در گوشه ها و مرکز سلول قرار دارند و زوایا ۹۰ درجه اند.',
                            'اتم ها در مرکز وجوه قرار دارند و در مرکز سلول اتمی نیست.',
                            'اتم ها در صفحه افقی هم فاصله اند ولی در جهت عمودی هم فاصله نیستند.',
                            'اتم ها در زنجیره های کووالانسی متصل و بدون الکترون آزادند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: در BCC، زوایا ۹۰ درجه اند، اتم ها در گوشه ها و یک اتم در مرکز unit cell قرار دارد. رد گزینه ها: ب توصیف FCC است. ج توصیف HCP است. د مربوط به polymerهای covalent chain است و نه lattice فلزی BCC.',
                    ],
                    [
                        'question' => 'آلیاژی از gold و palladium بررسی می شود. بر اساس توضیح فصل، انتظار غالب از crystal array آن چیست؟',
                        'options' => [
                            'Hexagonal close-packed',
                            'Body-centered cubic',
                            'Face-centered cubic',
                            'Amorphous cross-linked network',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: متن می گوید most pure metals and alloys of gold، palladium، cobalt و nickel دارای FCC array هستند. رد گزینه ها: الف برای titanium ذکر شده است. ب برای iron و بسیاری از iron alloys رایج است. د شبکه amorphous cross-linked مربوط به فلزات نیست.',
                    ],
                    [
                        'question' => 'در مقایسه sodium یا potassium با gold یا platinum، دلیل اصلی تفاوت تمایل به خوردگی در فصل چگونه توضیح داده شده است؟',
                        'options' => [
                            'تفاوت در رنگ و reflectivity سطحی فلزات',
                            'تفاوت در قدرت metallic force و انرژی solvating یون آزادشده',
                            'تفاوت در قابلیت تبدیل C=C به C-C هنگام واکنش',
                            'تفاوت در تعداد grainهای تصادفی داخل ساختار',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: خوردگی به strength metallic force، freedom of valence electron و energy gained by solvation of released ion وابسته معرفی شده است. رد گزینه ها: الف رنگ و reflectivity به جذب و emission نور مربوط اند. ج تغییر C=C به C-C مربوط به polymerization است. د grainها می توانند خواص جهت دار را میانگین کنند، نه تمایل sodium/potassium به خوردگی را توضیح دهند.',
                    ],
                    [
                        'question' => 'اگر در یک single crystal ویژگی هایی مانند conductivity و strength در جهت های مختلف متفاوت باشد، این پدیده با کدام اصطلاح توصیف می شود؟',
                        'options' => [
                            'Isotropy',
                            'Polymerization',
                            'Anisotropy',
                            'Solvation',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: متن nonuniformity of directional properties را anisotropy می نامد. رد گزینه ها: الف isotropy خلاف جهت مندی است. ب polymerization فرایند ساخت polymer است. د solvation به حل شدن یون آزادشده در خوردگی مربوط است.',
                    ],
                    [
                        'question' => 'چرا در آلیاژ دندانی معمولاً اثر جهت مندی خواص یک crystal منفرد کاهش می یابد؟',
                        'options' => [
                            'زیرا آلیاژ از مجموعه ای از grainهای با جهت گیری تصادفی تشکیل می شود.',
                            'زیرا valence electrons در آلیاژ به طور کامل حذف می شوند.',
                            'زیرا متالوییدها مانع تشکیل lattice می شوند.',
                            'زیرا dental alloys غالباً به unit cellهای hexagonal محدود می شوند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: dental alloy معمولاً مجموعه ای از grainهای randomly oriented است؛ بنابراین خواص جهت دار crystal منفرد در کل ماده averaged می شود. رد گزینه ها: ب حذف valence electrons با ماهیت فلز سازگار نیست. ج metalloids مانع lattice شدن آلیاژ نیستند. د آلیاژهای دندانی به HCP محدود نیستند؛ FCC و BCC نیز مطرح اند.',
                    ],
                    [
                        'question' => 'در یک آلیاژ، ایجاد ساختار fine-grained از نگاه فصل ۷ چه مزیتی دارد؟',
                        'options' => [
                            'افزایش solubility در آب برای آزادسازی یون ها',
                            'ایجاد خواص یکنواخت تر در جهت های مختلف',
                            'حذف کامل metallic bond از lattice',
                            'تبدیل شبکه فلزی به ساختار thermoset',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: فصل fine-grained structure را برای تشویق uniform properties in any direction مطلوب می داند. رد گزینه ها: الف افزایش solubility هدف fine-graining نیست. ج metallic bond حذف نمی شود. د ساختار فلز به thermoset تبدیل نمی شود.',
                    ],
                    [
                        'question' => 'در توضیح علت ductility و malleability فلزات، کدام عبارت دقیق تر است؟',
                        'options' => [
                            'پیوندهای یونی جهت دار اجازه شکست کنترل شده می دهند.',
                            'مراکز اتمی می توانند در همان lattice به موقعیت های جدید بلغزند.',
                            'chains پلیمری در اثر حرارت نرم و با سرد شدن سخت می شوند.',
                            'flaws سطحی باعث توقف slow crack growth می شوند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: ductility و malleability تا حد زیادی از ability of atomic centers to slide into new positions within the same crystal lattice ناشی می شود. رد گزینه ها: الف پیوند ionic جهت دار در ceramics مطرح تر است و لغزش فلزی را توضیح نمی دهد. ج توصیف thermoplastics است. د مربوط به ceramics و crack growth است.',
                    ],
                    [
                        'question' => 'در شکل پذیری فلزات، نقش dislocation چیست؟',
                        'options' => [
                            'حرکت هم زمان صفحات اتمی متعدد را لازم می کند.',
                            'لغزش را صفحه به صفحه ممکن و انرژی تغییر شکل را کمتر می کند.',
                            'باعث تبدیل lattice فلزی به ceramic oxide می شود.',
                            'مانع averaging خواص در grainهای مختلف می شود.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: dislocationها اجازه می دهند مراکز اتمی یک plane در هر بار از کنار هم بلغزند و انرژی تغییر شکل بسیار کمتر شود. رد گزینه ها: الف دقیقاً خلاف متن است؛ حرکت هم زمان planes متعدد لازم نیست. ج dislocation فلز را به ceramic تبدیل نمی کند. د dislocation درباره لغزش و deformation است، نه averaging خواص grainها.',
                    ],
                    [
                        'question' => 'در سناریویی که impurity مسیر حرکت dislocation را می بندد، پیامد قابل انتظار بر اساس فصل چیست؟',
                        'options' => [
                            'lattice بدون crack به شکل پذیری ادامه می دهد.',
                            'آزادشدن valence electron متوقف و خوردگی حذف می شود.',
                            'تجمع dislocationها می تواند crack موضعی و سپس شکست ایجاد کند.',
                            'ساختار به thermoplastic با flow دمای پایین تبدیل می شود.',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: impurity می تواند حرکت dislocation را متوقف کند؛ تجمع dislocationها سبب rupture موضعی lattice و آغاز crack می شود. رد گزینه ها: الف وقتی impurity مانع شود، لغزش بدون crack ادامه نمی یابد. ب موضوع خوردگی و الکترون آزاد نیست. د thermoplastic شدن پلیمر به این فرایند ربطی ندارد.',
                    ],
                    [
                        'question' => 'کدام توصیف درباره metallic bond درست تر است؟',
                        'options' => [
                            'پیوندی بین chains پلیمری است که با cross-linking ایجاد می شود.',
                            'پیوندی primary است که از نگه داشتن مراکز مثبت اتمی به وسیله الکترون های آزاد پدید می آید.',
                            'پیوندی ionic است که از oxides فلزی در dental porcelain حاصل می شود.',
                            'پیوندی است که هنگام اتصال ceramic particle به resin matrix با coupling agent ایجاد می شود.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: metallic bond یک primary bond است که در آن الکترون های آزاد مراکز اتمی مثبت را کنار هم نگه می دارند. رد گزینه ها: الف cross-linking مربوط به polymers است. ج ionic bonds در ceramics مطرح شده اند. د coupling agent مربوط به composite است، نه تعریف metallic bond.',
                    ],
                    [
                        'question' => 'اگر یک فلز به دلیل mobility الکترون ها نور را جذب و دوباره emit کند، کدام ویژگی بالینی یا ظاهری بیشتر توضیح داده می شود؟',
                        'options' => [
                            'opacity و reflectivity',
                            'translucency کنترل شده',
                            'low density',
                            'glass transition point پایین',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: opacity و reflective nature فلزات از توانایی valence electrons برای absorb و emit نور ناشی می شود. رد گزینه ها: ب تغییر translucency بیشتر درباره ceramics و composites است. ج low density با metals ناسازگار است. د glass transition point پایین مربوط به polymers است.',
                    ],
                    [
                        'question' => 'در طراحی ابزار یا restoration فلزی که قرار است ماشین کاری شود، کدام مجموعه ویژگی از کلاس فلز انتظار می رود؟',
                        'options' => [
                            'formability، low density، lowest stiffness، low long-term aqueous stability',
                            'brittleness، poor thermal/electrical conduction، low toughness، linear stress-strain',
                            'ductility/malleability، conductivity، high density، polishable luster',
                            'ceramic filler binding، moderate stiffness، sparing solubility، polymerization shrinkage',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: متن فلزات را ductile/malleable، conductors، high density، fracture resistant و polishable to a luster معرفی می کند. رد گزینه ها: الف توصیف polymers است. ب توصیف ceramics است. د توصیف composites است.',
                    ],
                    [
                        'question' => 'کدام statement درباره metalloids مطابق فصل ۷ است؟',
                        'options' => [
                            'مانند metals free positive ions پایدار تشکیل می دهند.',
                            'شامل carbon، silicon و boron هستند و در بسیاری از dental alloys اهمیت دارند.',
                            'از نظر آرایش اتمی همان thermoset polymers محسوب می شوند.',
                            'در جدول تناوبی بخش nonmetal با کاربرد محدود در آلیاژها هستند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: متن metalloids را شامل carbon، silicon و boron می داند و conductive/electronic properties آن ها را برای بسیاری از dental alloys مهم می شمارد. رد گزینه ها: الف می گوید metalloids مانند metals free positive ions می دهند، در حالی که متن این را برای metalloids تایید نمی کند. ج thermoset polymer نیستند. د آن ها در فصل به عنوان metalloids با اهمیت در alloys مطرح اند، نه nonmetals بی اهمیت.',
                    ],
                    [
                        'question' => 'یک عنصر در محلول به یون مثبت تبدیل می شود و الکترون آزاد می کند. این توصیف در فصل ۷ معیار شناخت کدام گروه است؟',
                        'options' => [
                            'Metal',
                            'Ceramic',
                            'Polymer',
                            'Composite',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فصل metal را هر عنصری تعریف می کند که در solution به صورت positive ion یونیزه شود و electrons آزاد کند. رد گزینه ها: ب ceramic ماده غیرآلی غیر فلزی fired است. ج polymer از merهای تکراری ساخته می شود. د composite ترکیب دو یا چند کلاس ماده است.',
                    ],
                    [
                        'question' => 'در انتخاب بین چهار کلاس biomaterial برای intracoronal و extracoronal restorations، کدام گزاره با فصل سازگارتر است؟',
                        'options' => [
                            'چند کلاس می توانند ماده مناسب ارایه دهند، نه یک کلاس ثابت.',
                            'فلزات به دلیل سابقه بالینی گزینه ثابت محسوب می شوند.',
                            'پلیمرها به دلیل formability گزینه پیش فرض اند.',
                            'ceramicها به علت امکان casting و machining جانشین کلاس های دیگر هستند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: متن می گوید برای بعضی کاربردها مانند intracoronal و extracoronal restorations چند کلاس می توانند materials مناسب ارایه دهند. رد گزینه ها: ب متن فلزات را گزینه یگانه برای این کاربردها نمی داند. ج polymers گزینه یگانه نیستند. د ceramics جانشین کلاس های دیگر معرفی نشده اند.',
                    ],
                    [
                        'question' => 'یک ماده دندانی با low density، low hardness نسبت به فلزات، poor temperature/electrical conduction و lowest stiffness در میان چهار کلاس توصیف می شود. کدام کلاس محتمل تر است؟',
                        'options' => [
                            'Metals and alloys',
                            'Polymers',
                            'Ceramics',
                            'Metal-ceramic composites',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: فصل polymers را low density و low hardness نسبت به metals، poor conductors و دارای lowest stiffness در میان چهار کلاس معرفی می کند. رد گزینه ها: الف metals high density و good conductors هستند. ج ceramics stiff و brittle هستند، نه lowest stiffness. د metal-ceramic composite چنین توصیف مستقیمی در این بخش ندارد.',
                    ],
                    [
                        'question' => 'در فصل ۷، کدام فهرست کاربردها بیشترین تطابق را با polymers دارد؟',
                        'options' => [
                            'implants، direct restorations، instruments، metal frameworks',
                            'sealants، cements، impressions، denture bases، athletic mouth protectors',
                            'denture teeth، full-coverage crowns، particulate fillers، veneers on metal frameworks',
                            'sand، gravel، portland cement، aerospace metal-ceramic structures',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: فصل polymers را برای sealants، cements، impressions، denture bases و athletic mouth protectors از جمله کاربردهای متعدد معرفی می کند. رد گزینه ها: الف مربوط به metals و alloys است. ج مربوط به ceramics است. د مثال های composites خارج از dentistry و concrete هستند.',
                    ],
                    [
                        'question' => 'چرا polymers در فصل ۷ به عنوان یکی از اجزای مهم composites نیز مطرح شده اند؟',
                        'options' => [
                            'چون معمولاً به عنوان matrix برای اتصال ceramic particles عمل می کنند.',
                            'چون به صورت FCC array با grainهای تصادفی قرار می گیرند.',
                            'چون از kaolin، quartz و feldspar ساخته می شوند.',
                            'چون در اثر machining surface crack ایجاد می کنند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: در composites دندانی، polymer معمولاً matrix است که ceramic particles را bind می کند. رد گزینه ها: ب FCC array و grainها مربوط به فلزات اند. ج kaolin، quartz و feldspar مربوط به porcelain است. د machining surface crack مربوط به ceramics است.',
                    ],
                    [
                        'question' => 'در تعریف chemical composition پلیمر، mer به چه چیزی اشاره دارد؟',
                        'options' => [
                            'کوچک ترین repeating chemical structural unit در polymer',
                            'مرکز اتمی مثبت در metallic lattice',
                            'crystalline phase تقویت کننده در ceramic',
                            'عامل اتصال بین ceramic particle و polymer matrix',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: mer ساده ترین repeating chemical structural unit در polymer است. رد گزینه ها: ب atomic center در metallic lattice است. ج crystalline phase مربوط به ceramics است. د coupling agent مربوط به composite interface است.',
                    ],
                    [
                        'question' => 'اگر پلیمر از methyl methacrylate ساخته شود، نام poly(methyl methacrylate) چگونه از متن قابل تفسیر است؟',
                        'options' => [
                            'پلیمر از واحدهای ساختاری مشتق از methyl methacrylate تشکیل شده است.',
                            'پلیمر شامل سه واحد متفاوت methyl، ethyl و propyl است.',
                            'پلیمر در اثر firing مواد غیرآلی در دمای بالا ایجاد شده است.',
                            'پلیمر یک آلیاژ با valence electrons آزاد است.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: متن poly(methyl methacrylate) را polymer دارای chemical structural units derived from methyl methacrylate توصیف می کند. رد گزینه ها: ب ترکیب سه واحد متفاوت با terpolymer مرتبط است. ج تعریف ceramic است. د توصیف فلز یا آلیاژ است.',
                    ],
                    [
                        'question' => 'Monomer در زبان فصل ۷ به کدام مفهوم نزدیک تر است؟',
                        'options' => [
                            'one part که polymer از آن ساخته می شود',
                            'one grain که آلیاژ دندانی از آن تشکیل می شود',
                            'one flaw که crack ceramic از آن آغاز می شود',
                            'one particle که composite را تقویت می کند',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: monomer در متن moleculeهایی است که polymer از آن ساخته می شود و معنای one part دارد. رد گزینه ها: ب grain مربوط به alloy است. ج flaw مربوط به شکست ceramics است. د particle تقویت کننده مربوط به composite است.',
                    ],
                    [
                        'question' => 'نمونه ای دارای دو نوع chemical unit در polymer molecule است. این ماده در طبقه بندی متن چه نام می گیرد؟',
                        'options' => [
                            'Homopolymer',
                            'Copolymer',
                            'Terpolymer',
                            'Porcelain',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: polymerهایی با دو یا چند chemical unit متفاوت copolymer نامیده می شوند. رد گزینه ها: الف homopolymer یک نوع mer دارد. ج terpolymer سه unit دارد. د porcelain یک نوع ceramic composition است.',
                    ],
                    [
                        'question' => 'اگر یک polymer molecule سه chemical unit متفاوت داشته باشد، اصطلاح مناسب تر چیست؟',
                        'options' => [
                            'Block polymer',
                            'Cross-linked polymer',
                            'Terpolymer',
                            'Metalloid polymer',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: اگر polymer دارای سه different units باشد، متن آن را terpolymer می نامد. رد گزینه ها: الف block polymer به آرایش segmental merها اشاره دارد. ب cross-linked polymer به network structure مربوط است. د اصطلاح مطرح شده در فصل نیست.',
                    ],
                    [
                        'question' => 'در فرمول های پلیمری، n، m و p چه نقشی دارند؟',
                        'options' => [
                            'بار الکتریکی هر mer را نشان می دهند.',
                            'تعداد متوسط mer unitهای مختلف در مولکول را نمایش می دهند.',
                            'زاویه بین محورهای lattice را تعیین می کنند.',
                            'اندازه flaw طبیعی ceramic را بیان می کنند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: n، m و p میانگین تعداد mer unitهای مختلف در polymer molecule را نشان می دهند. رد گزینه ها: الف بار الکتریکی merها را نشان نمی دهند. ج زاویه lattice فلزی نیستند. د اندازه flaw ceramic نیستند.',
                    ],
                    [
                        'question' => 'در پلیمرهای معمولی، mer unitها غالباً چگونه در chain قرار می گیرند؟',
                        'options' => [
                            'با فاصله گذاری random در طول chain',
                            'در مراکز وجوه unit cell',
                            'به صورت صفحات لغزشی یک lattice فلزی',
                            'در نواحی crack initiation سطحی',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: متن می گوید در normal polymers mer units در طول polymer chain به صورت random spaced هستند. رد گزینه ها: ب centers of faces مربوط به FCC است. ج لغزش صفحات مربوط به metals است. د crack initiation مربوط به ceramics است.',
                    ],
                    [
                        'question' => 'در block polymer، تفاوت اصلی با random copolymer چیست؟',
                        'options' => [
                            'merهای مختلف در بخش های بزرگ تر و پشت سرهم از هر نوع قرار می گیرند.',
                            'merها یکسان اند و بخش متفاوتی وجود ندارد.',
                            'merها با firing در دمای بالا به oxide تبدیل می شوند.',
                            'merها مانند grainهای فلزی جهت گیری تصادفی دارند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: در block polymer تعداد زیادی از یک نوع mer به تعداد زیادی از نوع دیگر متصل می شود و segments یا blocks تشکیل می دهد. رد گزینه ها: ب homopolymer را توصیف می کند. ج تعریف ceramic processing است. د grain orientation مربوط به alloy است.',
                    ],
                    [
                        'question' => 'stereospecific polymers در متن فصل با کدام ویژگی شناخته می شوند؟',
                        'options' => [
                            'وجود spatial arrangement ویژه merها نسبت به واحدهای مجاور',
                            'وجود atom در مرکز unit cell و گوشه های lattice',
                            'وجود porosity داخلی در glass-ceramic restoration',
                            'وجود cement binder میان sand و gravel',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: stereospecific polymers دارای spatial arrangement ویژه mer units نسبت به adjacent units هستند. رد گزینه ها: ب BCC را توصیف می کند. ج flaw یا porosity ceramic است. د مثال composite در concrete است.',
                    ],
                    [
                        'question' => 'در polymerization معمول، کدام تغییر پیوندی در فصل مطرح شده است؟',
                        'options' => [
                            'تبدیل C=C double bonds به C-C single bonds',
                            'تبدیل ionic bond به metallic bond',
                            'تبدیل FCC array به HCP array',
                            'تبدیل flaw سطحی به crystalline phase تقویت کننده',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: متن توضیح می دهد که در polymerization، C=C double bonds معمولاً به C-C single bonds تبدیل می شوند. رد گزینه ها: ب ionic-to-metallic bond در فصل برای polymerization نیامده است. ج تبدیل FCC به HCP مطرح نیست. د crystalline phase و flaw مربوط به ceramics است.',
                    ],
                    [
                        'question' => 'اگر یک ماده از نظر بالینی به صورت obturator for cleft palate یا root canal filling material استفاده شود، کدام کلاس در فصل به چنین کاربردهایی نسبت داده شده است؟',
                        'options' => [
                            'Metals',
                            'Polymers',
                            'Ceramics',
                            'Concrete composites',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: فصل obturators for cleft palates و root canal filling materials را در فهرست کاربردهای polymers قرار می دهد. رد گزینه ها: الف metals برای instruments، implants و restorations آمده اند. ج ceramics برای crowns، denture teeth و fillers آمده اند. د concrete composite مثال خارج از دندان پزشکی است.',
                    ],
                    [
                        'question' => 'چرا فلزات در بسیاری از کاربردهای بلندمدت دندانی با وجود تقاضا برای tooth-colored materials باقی مانده اند؟',
                        'options' => [
                            'چون قدرت، سختی، مقاومت به شکست و longevity قابل توجه دارند.',
                            'چون کمترین stiffness و کمترین glass transition point را دارند.',
                            'چون مانند ceramics poor electrical conductors هستند.',
                            'چون مانند composites از دو یا چند کلاس تشکیل می شوند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: متن با وجود تمایل به tooth-colored materials، فلزات را به دلیل strength، stiffness، fracture resistance و longevity در کاربردهای بلندمدت مهم می داند. رد گزینه ها: ب ویژگی polymers است. ج ceramics poor conductors هستند؛ فلزات good conductors هستند. د تعریف composites است.',
                    ],
                    [
                        'question' => 'کدام ویژگی در متن فصل به عنوان تفاوت polymers با فلزات و ceramicها درباره محیط آبی مطرح شده است؟',
                        'options' => [
                            'بیشترین long-term stability در aqueous environment',
                            'کمترین long-term stability در aqueous environment',
                            'عدم تاثیر molecular weight distribution بر properties',
                            'عدم امکان تغییر opacity یا translucency',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: فصل polymers را در میان چهار کلاس دارای lowest long-term stability in an aqueous environment معرفی می کند. رد گزینه ها: الف خلاف متن است. ج متن molecular weight distribution را موثر بر خواص می داند. د polymers می توانند translucent یا opaque ساخته شوند.',
                    ],
                    [
                        'question' => 'در مقایسه pure metal با dental alloy، چرا جمله «pure metals have no net charge» درست است؟',
                        'options' => [
                            'بار مثبت مراکز اتمی با الکترون های آزاد منفی هم زمان خنثی می شود.',
                            'یون های آزاد با solvation energy بالا در آب حل می شوند.',
                            'unit cellها به دلیل حضور impurityها شکسته می شوند.',
                            'mer unitها با C-C single bonds به هم متصل می شوند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: در pure metals مراکز مثبت اتمی توسط الکترون های آزاد نگه داشته و هم زمان خنثی می شوند؛ بنابراین net charge ندارند. رد گزینه ها: ب مربوط به corrosion در solution است، نه خنثی بودن pure metal. ج impurity و fracture را توضیح می دهد. د متعلق به polymerization است.',
                    ],
                    [
                        'question' => 'اگر در یک فلز تعداد valence electronهای هر atomic center افزایش یابد، فصل کدام پیامد را برای melting point توضیح می دهد؟',
                        'options' => [
                            'کاهش melting point به علت حذف metallic bond',
                            'افزایش melting point تا حدی به علت covalent character بیشتر در metallic bond',
                            'تبدیل فلز به polymer با glass transition پایین',
                            'ایجاد slow crack growth در محیط مرطوب',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: فصل می گوید افزایش valence electrons مقداری covalent character در metallic bond ایجاد می کند و به higher melting points کمک می کند. رد گزینه ها: الف خلاف جهت توضیح متن است. ج به polymer مربوط است. د slow crack growth مربوط به ceramics در محیط مرطوب است.',
                    ],
                    [
                        'question' => 'در crack propagation مثال فولاد، نتیجه آموزشی متن چیست؟',
                        'options' => [
                            'وجود crack نیروی لازم برای ادامه شکست را بسیار کم می کند.',
                            'crystal flawless با کمترین نیرو می شکند.',
                            'impurity ductility را بدون کاهش مقاومت افزایش می دهد.',
                            'moving dislocationها بدون نقص، fracture را آسان تر از crack می کنند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: مثال فولاد نشان می دهد وجود crack نیروی لازم برای ادامه شکست را بسیار کمتر از حالت بدون crack یا single flawless crystal می کند. رد گزینه ها: ب crystal flawless در مثال به بیشترین نیرو نیاز دارد. ج impurity می تواند مانع dislocation و منشا crack شود، نه الزاماً ductility را افزایش دهد. د dislocationها معمولاً deformation را آسان می کنند؛ crack ادامه شکست را آسان تر می کند.',
                    ],
                    [
                        'question' => 'کدام گزینه درباره شواهد بالینی فلزات در فصل دقیق تر است؟',
                        'options' => [
                            'برای این کلاس extensiveترین scientific literature درباره clinical performance وجود دارد.',
                            'شواهد بالینی این کلاس کمتر از polymers و ceramics است.',
                            'شواهد بالینی فلزات بیشتر برای رنگ و translucency مطرح شده است.',
                            'شواهد بالینی در فصل به علت قدیمی بودن کلاس کنار گذاشته شده است.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فصل می گوید evidence in scientific literature of clinical performance برای metals and alloys extensiveترین است. رد گزینه ها: ب خلاف متن است. ج شواهد بالینی به عملکرد clinical مربوط است، نه رنگ به عنوان محور اصلی. د متن کلاس فلزات را کنار نمی گذارد.',
                    ],
                    [
                        'question' => 'در متن فصل، کدام مقایسه بین classes درست تر است؟',
                        'options' => [
                            'Metals کم چگال تر از polymers و ceramics هستند.',
                            'Polymers سخت تر و چگال تر از metals هستند.',
                            'Ceramics مانند metals رفتار plastic آشکار دارند.',
                            'Metals نسبت به classes دیگر density بالاتری دارند و good conductors هستند. نیمه دوم فصل ۷',
                        ],
                        'correctIndex' => 3,
                        'explanation' => '**پاسخ درست:** گزینه د
دلیل درست بودن: فصل metals را higher in density و good electrical/thermal conductors معرفی می کند. رد گزینه ها: الف خلاف high density است. ب خلاف توصیف polymers است. ج ceramics plastic behavior اندکی دارند و brittle هستند. نیمه دوم فصل ۷',
                    ],
                ],
            ],
            [
                'slug' => '7-2',
                'path' => '/exams/craig-dental-materials/6-10/7-2/',
                'questionCount' => 40,
                'label' => 'فصل ۷ - قسمت دوم',
                'title' => 'آزمون فصل ۷ - قسمت دوم',
                'subtitle' => '۴۰ سؤال چهارگزینه‌ای از مجموعه کریگ.',
                'description' => 'مرور ۴۰ سؤال از مجموعه کریگ.',
                'eyebrow' => 'کریگ | فصول ۶ تا ۱۰ | فصل ۷ - قسمت دوم',
                'backHref' => '/exams/craig-dental-materials/6-10/',
                'backLabel' => 'بازگشت به فصول ۶ تا ۱۰ کریگ',
                'autoAdvance' => true,
                'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'siteSubtitle' => 'آزمون‌ها',
                'siteBadge' => 'آزمون رفرنسی',
                'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'questions' => [
                    [
                        'question' => 'دو نمونه poly(methyl methacrylate) ترکیب شیمیایی یکسان دارند، اما خواص فیزیکی آن ها بسیار متفاوت است. کدام توضیح بر اساس فصل محتمل تر است؟',
                        'options' => [
                            'تفاوت در molecular weight distribution',
                            'تفاوت در نوع lattice بین BCC و FCC',
                            'تفاوت در مقدار kaolin و feldspar',
                            'تفاوت در flaw طبیعی 20 تا 50 میکرومتر',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فصل تاکید می کند دو specimen با composition یکسان می توانند به علت تفاوت در molecular weight distribution خواص فیزیکی متفاوت داشته باشند. رد گزینه ها: ب BCC/FCC به فلزات مربوط است. ج kaolin/feldspar مربوط به porcelain است. د flaw size مربوط به ceramics است.',
                    ],
                    [
                        'question' => 'degree of polymerization در فصل ۷ به چه معناست؟',
                        'options' => [
                            'تعداد کل merها در یک polymer molecule',
                            'تعداد grainهای یک alloy در هر واحد حجم',
                            'نسبت crystalline phase به glassy matrix',
                            'تعداد ceramic particles متصل به coupling agent',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: degree of polymerization به total number of mers in a polymer molecule تعریف شده است. رد گزینه ها: ب grain count اصطلاح فلزی است. ج نسبت phaseها مربوط به ceramic microstructure است. د تعداد particles متصل به coupling agent تعریف degree of polymerization نیست.',
                    ],
                    [
                        'question' => 'افزایش molecular weight در پلیمر ساخته شده از یک monomer، به طور کلی چه اثری در فصل بیان شده است؟',
                        'options' => [
                            'softening/melting point و stiffness را افزایش می دهد.',
                            'flow در دماهای بالا را نسبت به cross-linked polymer کاهش نمی دهد.',
                            'material را به یک ceramic oxide تبدیل می کند.',
                            'corrosion را از طریق solvation energy بیشتر افزایش می دهد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: متن می گوید higher molecular weight به higher softening/melting points و stiff تر شدن polymer منجر می شود. رد گزینه ها: ب flow cross-linked polymers را مخدوش بیان می کند. ج پلیمر را به ceramic oxide تبدیل نمی کند. د corrosion فلزات به solvation و metallic force مربوط است.',
                    ],
                    [
                        'question' => 'اگر پلیمر پس از گرم شدن نرم شود و با سرد شدن solidify کند و این روند تکرارپذیر باشد، کدام طبقه بندی مناسب است؟',
                        'options' => [
                            'Thermoset',
                            'Thermoplastic',
                            'Cross-linked porcelain',
                            'Metal-ceramic composite',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: thermoplastic polymers با heating نرم و با cooling solidify می شوند و این فرایند repeatable است. رد گزینه ها: الف thermoset با reheating نرم نمی شود. ج اصطلاح نامعتبر است. د composite نیست.',
                    ],
                    [
                        'question' => 'پلیمرهایی که در fabrication جامد می شوند و با reheating نرم نمی شوند، بیشتر به کدام علت چنین رفتاری دارند؟',
                        'options' => [
                            'formation of cross-linked spatial structure',
                            'growth of natural surface flaws',
                            'mobility of valence electrons',
                            'condensation inclusions در مرحله ساخت',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: thermosets معمولاً به علت cross-linking reaction و formation of spatial structure nonfusible می شوند. رد گزینه ها: ب surface flaw مربوط به ceramics است. ج valence electron mobility مربوط به metals است. د condensation inclusions fabrication defects در ceramics هستند.',
                    ],
                    [
                        'question' => 'کدام گزینه نمونه thermoplastic از متن فصل است؟',
                        'options' => [
                            'Poly(methyl methacrylate)',
                            'Cross-linked poly(methyl methacrylate)',
                            'Silicone',
                            'Dimethacrylate',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: poly(methyl methacrylate) و polyethylene-polyvinylacetate به عنوان نمونه thermoplastic ذکر شده اند. رد گزینه ها: ب cross-linked PMMA نمونه thermoset است. ج silicones در فهرست thermosets آمده اند. د dimethacrylates نیز در thermosetting examples آمده اند.',
                    ],
                    [
                        'question' => 'کدام گزینه نمونه thermoset یا thermosetting polymer در متن است؟',
                        'options' => [
                            'Polyethylene-polyvinylacetate',
                            'Poly(methyl methacrylate) thermoplastic',
                            'Cross-linked poly(methyl methacrylate)',
                            'Random methyl methacrylate-ethyl methacrylate copolymer',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: cross-linked poly(methyl methacrylate) از نمونه های thermosetting/thermoset متن است. رد گزینه ها: الف و ب در متن برای thermoplastics آمده اند. د random copolymer به ترکیب merها اشاره دارد، نه نمونه مشخص thermoset ذکرشده.',
                    ],
                    [
                        'question' => 'در مقایسه ساختارهای polymer، کدام توصیف برای cross-linked molecules مناسب تر است؟',
                        'options' => [
                            'separate and discrete molecules با flow مشابه linear polymers',
                            'network structure که می تواند یک giant polymeric molecule ایجاد کند',
                            'crystalline array پیوسته در سه بعد با valence electrons آزاد',
                            'oxide of metals با processing by firing',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: cross-linked molecules به صورت network structure هستند و ممکن است یک giant polymeric molecule ایجاد کنند. رد گزینه ها: الف برای linear/branched molecules مناسب تر است. ج تعریف فلز است. د تعریف ceramic است.',
                    ],
                    [
                        'question' => 'در کدام حالت، generalization فصل درباره جذب مایعات بیشتر به نفع مقاومت نسبی در برابر جذب است؟',
                        'options' => [
                            'Linear polymer',
                            'Branched polymer',
                            'Cross-linked polymer',
                            'Random copolymer بدون cross-linking',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن: متن می گوید برخی cross-linked polymers مایعات را به آسانی linear یا branched materials جذب نمی کنند. رد گزینه ها: الف و ب در مقایسه متن readily liquid absorption بیشتری نسبت به cross-linked دارند. د random copolymer بدون cross-linking الزاماً چنین مزیت نسبی ندارد.',
                    ],
                    [
                        'question' => 'اگر هدف تغییر physical و mechanical properties یک polymer باشد، کدام مجموعه متغیرها طبق فصل قابل دستکاری است؟',
                        'options' => [
                            'composition، molecular weight، molecular-weight distribution، spatial arrangement',
                            'flaw size، surface crack، cyclic loading، humidity',
                            'BCC، FCC، HCP، grain orientation',
                            'kaolin، quartz، feldspar، firing shade',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فصل تغییر chemical composition، molecular weight، molecular-weight distribution و spatial arrangement merها را راه تغییر خواص polymer می داند. رد گزینه ها: ب مربوط به ceramic flaws و محیط بارگذاری است. ج مربوط به فلزات است. د مربوط به porcelain composition و shade است.',
                    ],
                    [
                        'question' => 'اصطلاح ceramic در فصل به چه ماده ای اشاره دارد؟',
                        'options' => [
                            'محصولی از nonmetallic inorganic material که معمولاً با firing در دمای بالا processing می شود.',
                            'مولکولی با merهای تکراری و C-C backbone',
                            'آلیاژی با valence electrons آزاد و grainهای تصادفی',
                            'ترکیب polymer matrix و ceramic particles برای filling material',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: ceramic محصولی از nonmetallic inorganic material است که معمولاً با firing در دمای بالا processed می شود تا خواص مطلوب ایجاد شود. رد گزینه ها: ب تعریف polymer است. ج تعریف metal است. د تعریف composite است.',
                    ],
                    [
                        'question' => 'ماده ای سخت، stiff، poor thermal/electrical conductor و با toughness کمتر از metals است و stress-strain curve آن plastic strain اندکی دارد. کدام کلاس توصیف می شود؟',
                        'options' => [
                            'Polymer',
                            'Ceramic',
                            'Metal',
                            'Unfilled monomer',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: ceramics سخت، stiff، low toughness نسبت به فلزات، poor thermal/electrical conductors و با plastic behavior اندک هستند. رد گزینه ها: الف polymers lowest stiffness و low hardness دارند. ج metals conductors و ductile/malleable هستند. د unfilled monomer در این بخش چنین کلاس مستقلی نیست.',
                    ],
                    [
                        'question' => 'چرا ceramics در مقایسه با فلزات و پلیمرها brittle در نظر گرفته می شوند؟',
                        'options' => [
                            'چون معمولاً رفتار plastic کمی نشان می دهند و stress-strain curve آن ها غالباً linear است.',
                            'چون valence electronهای آزاد اجازه لغزش صفحات را می دهند.',
                            'چون mer unitهای random در chain دارند.',
                            'چون particleها توسط Bis-GMA به هم متصل می شوند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: ceramics plastic behavior کمی دارند و stress-strain curves آن ها generally linear with no plastic strain است؛ بنابراین brittle محسوب می شوند. رد گزینه ها: ب توصیف فلزات و لغزش است. ج توصیف polymer chains است. د توصیف dental composites است.',
                    ],
                    [
                        'question' => 'کدام کاربرد برای ceramics طبق فصل ذکر شده است؟',
                        'options' => [
                            'orthodontic elastics و athletic mouth protectors',
                            'full- and partial-coverage crowns و denture teeth',
                            'root canal filling material و sealants',
                            'metal instruments for cleaning teeth',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: فصل ceramics را برای full- and partial-coverage crowns، denture teeth و particulate fillers در resin matrix composites ذکر می کند. رد گزینه ها: الف و ج کاربردهای polymers هستند. د مربوط به metals and alloys است.',
                    ],
                    [
                        'question' => 'اصطلاح porcelain در فصل ۷ نسبت به ceramic چه وضعیتی دارد؟',
                        'options' => [
                            'اصطلاحی گسترده تر از ceramic برای طیف وسیعی از oxides فلزی است.',
                            'یک compositional range خاص از ceramicهای ساخته شده از kaolin، quartz و feldspar است.',
                            'نام دیگر polymer matrix در resin composites است.',
                            'نام alloy دندانی با FCC array است.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: porcelain یک compositional range خاص از ceramic materials است که از mixing kaolin، quartz و feldspar و firing در دمای بالا ساخته می شود. رد گزینه ها: الف porcelain گسترده تر از ceramic نیست. ج polymer matrix نیست. د alloy با FCC نیست.',
                    ],
                    [
                        'question' => 'کدام کاربرد dental porcelain در متن ذکر شده است؟',
                        'options' => [
                            'veneers on metal frameworks و minimally prepared anterior teeth',
                            'implant instruments و wrought wires',
                            'obturators for cleft palates و impressions',
                            'coupling agents و resin cement binders',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: dental porcelain برای veneers on metal frameworks، minimally prepared anterior teeth و denture teeth ذکر شده است. رد گزینه ها: ب کاربردهای فلزی است. ج کاربردهای polymers است. د coupling agents و resin cements مربوط به composites/adhesion هستند، نه porcelain کاربردی.',
                    ],
                    [
                        'question' => 'در یک ceramic restoration، کدام عامل ها properties ماده را تعیین می کنند؟',
                        'options' => [
                            'composition، microstructure و flaw population',
                            'valence electron mobility، solvation energy و grain averaging',
                            'monomer random spacing، n/m/p subscripts و degree of polymerization',
                            'portland cement، sand و gravel distribution',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: properties of dental ceramics به composition، microstructure و flaw population وابسته است. رد گزینه ها: ب مربوط به metals و corrosion است. ج مربوط به polymers است. د مثال concrete composite است.',
                    ],
                    [
                        'question' => 'reinforcing crystalline phase در dental ceramics چه چیزی را دیکته می کند؟',
                        'options' => [
                            'strength، resistance to crack propagation و optical properties',
                            'ionization to positive ions و metallic luster',
                            'thermoplastic softening و repeatable cooling',
                            'polymerization shrinkage stress و matrix strain',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: nature و amount of reinforcing crystalline phase، strength، resistance to crack propagation و optical properties را تعیین می کند. رد گزینه ها: ب به metals مربوط است. ج به thermoplastics مربوط است. د به resin composite polymerization مربوط است.',
                    ],
                    [
                        'question' => 'یک ceramic crown در محیط مرطوب تحت chewing بارگذاری تکراری می شود. کدام پدیده فصل می تواند survival probability را کاهش دهد؟',
                        'options' => [
                            'Slow crack growth',
                            'Metallic solvation',
                            'Thermoplastic recycling',
                            'Random copolymerization',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: repeated cyclic loading in humid environment شرایطی ایده آل برای extension defects/cracks است و slow crack growth survival probability ceramic restorations را کاهش می دهد. رد گزینه ها: ب metallic solvation خوردگی فلزات است. ج thermoplastic recycling در ceramics نیست. د random copolymerization مربوط به polymer chemistry است.',
                    ],
                    [
                        'question' => 'کدام جفت flaw در ceramics بر اساس فصل مطرح شده است؟',
                        'options' => [
                            'fabrication defects و surface cracks',
                            'mer defects و branch cracks',
                            'grain averaging و dislocations',
                            'Bis-GMA contraction و C=C conversion',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فصل ceramics را دارای دو population نقص می داند: fabrication defects و surface cracks. رد گزینه ها: ب اصطلاحات polymerی و crack را نامعتبر ترکیب کرده است. ج مربوط به فلزات است. د مربوط به resin composite polymerization است.',
                    ],
                    [
                        'question' => 'در شکست یک glass-ceramic restoration، اگر fracture initiation از سطح داخلی porous شروع شود، این با کدام منشا defect سازگارتر است؟',
                        'options' => [
                            'fabrication defect مانند void ناشی از sintering',
                            'surface crack ناشی از grinding بیرونی',
                            'metallic corrosion در اثر solvation',
                            'polymer chain branching',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: porosity داخلی در clinically failed glass-ceramic restorations به عنوان fracture initiation site مطرح شده و با voidهای generated during sintering سازگار است. رد گزینه ها: ب surface crack ناشی از machining/grinding است، نه porosity internal. ج corrosion فلزی نیست. د branch polymer مطرح نیست.',
                    ],
                    [
                        'question' => 'کدام عامل می تواند microcracks را در feldspathic porcelain هنگام cooling ایجاد کند؟',
                        'options' => [
                            'thermal contraction mismatch بین leucite crystals و glassy matrix',
                            'solvation energy بالای یون sodium در آب',
                            'random orientation of mers در polymer chain',
                            'lower binding forces بین bacteria و substrate',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: microcracks در feldspathic porcelains می تواند از thermal contraction mismatch بین leucite crystals و glassy matrix ایجاد شود. رد گزینه ها: ب مربوط به corrosion sodium/potassium است. ج مربوط به polymer chains است. د مربوط به biofilm و adhesion نیست.',
                    ],
                    [
                        'question' => 'اگر porcelain خیلی سریع سرد شود، کدام علت ذکرشده می تواند به microcracking کمک کند؟',
                        'options' => [
                            'thermal shock',
                            'graft branching',
                            'HCP packing',
                            'coupling hydrolysis',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: متن thermal shock در اثر cooling too rapidly را علت ممکن microcracks porcelain ذکر می کند. رد گزینه ها: ب graft branching ساختار polymer است. ج HCP packing مربوط به titanium است. د coupling hydrolysis در این متن به microcracking porcelain ربط داده نشده است.',
                    ],
                    [
                        'question' => 'در fabrication defects، inclusions اغلب به کدام خطای فرایندی مرتبط دانسته شده اند؟',
                        'options' => [
                            'improper cleaning of metal framework یا use of unclean instruments',
                            'افزایش molecular weight در polymer',
                            'بالا رفتن surface free energy در oral biofilm',
                            'انتخاب Bis-GMA به جای portland cement',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: inclusions اغلب به improper cleaning of the metal framework یا استفاده از unclean instruments نسبت داده شده اند. رد گزینه ها: ب molecular weight مربوط به polymers است. ج surface free energy از فصل biofilm نیست و در این فصل برای inclusions نیامده است. د Bis-GMA و concrete به composite analogy مربوط اند.',
                    ],
                    [
                        'question' => 'میانگین natural flaw size در ceramics طبق فصل در چه محدوده ای است؟',
                        'options' => [
                            '2 تا 5 μm',
                            '20 تا 50 μm',
                            '100 تا 150 μm',
                            '0.2 تا 0.5 μm',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن: فصل average natural flaw size را 20 تا 50 μm بیان می کند. رد گزینه ها: الف، ج و د با محدوده ذکرشده در متن تطابق ندارند.',
                    ],
                    [
                        'question' => 'CAD-CAM در بخش ceramics فصل ۷ به کدام روند نزدیک تر است؟',
                        'options' => [
                            'acquisition of digital images، computer-aided design و milling ceramic blanks',
                            'oxidation-reduction در محلول برای آزادسازی یون ها',
                            'conversion of C=C to C-C در polymerization',
                            'افزایش adhesion بین sand-gravel و portland cement',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: CAD-CAM در متن شامل digital images of tooth preparations، computer-aided design و milling machineable ceramic blanks است. رد گزینه ها: ب خوردگی فلزات است. ج polymerization است. د analogy concrete/composite است.',
                    ],
                    [
                        'question' => 'در dental resin composite، polymer و ceramic به ترتیب چه نقش هایی دارند؟',
                        'options' => [
                            'polymer به عنوان matrix و ceramic particles به عنوان reinforcing materials',
                            'polymer به عنوان crystalline phase و ceramic به عنوان solvent',
                            'polymer به عنوان grain و ceramic به عنوان dislocation',
                            'polymer به عنوان firing aid و ceramic به عنوان monomer',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: در dental composites، polymer matrix است و ceramic particles نقش reinforcing materials را دارند. رد گزینه ها: ب نقش ها را نادرست جابه جا و نامعتبر می کند. ج مفاهیم فلزی را وارد composite می کند. د firing/monomer با این نقش بندی سازگار نیست.',
                    ],
                    [
                        'question' => 'کدام مجموعه کاربردها برای polymer matrix composites در فصل ذکر شده است؟',
                        'options' => [
                            'sealants، intracoronal/extracoronal restorations، veneers، cements، core buildups',
                            'removable partial denture frameworks، metal instruments، implants، wires',
                            'kaolin-quartz-feldspar porcelain، denture teeth، metal veneers، refractory dies',
                            'orthodontic space maintainers، cleft palate obturators، impressions، root canal filling materials',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: polymer matrix composites برای sealants، restorations، provisional restorations، veneers، denture teeth، cements و core buildups ذکر شده اند. رد گزینه ها: ب مربوط به metals است. ج porcelain و ceramic contexts را با مواد دیگر مخلوط می کند. د فهرست کاربردهای polymers کلی است، نه polymer matrix composites.',
                    ],
                    [
                        'question' => 'به عنوان یک کلاس، dental composites در فصل چگونه توصیف شده اند؟',
                        'options' => [
                            'formable، قابل machine شدن، opaque یا translucent، moderate in stiffness/hardness',
                            'highest density، good electrical conductors، metallic luster، high corrosion tendency',
                            'low toughness، no plastic strain، poor thermal conductors، fired inorganic oxides',
                            'lowest stiffness، lowest long-term aqueous stability، lowest glass transition point',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فصل dental composites را formable، قابل machine شدن، opaque یا translucent، moderate in stiffness and hardness، thermal/electrical insulators و sparingly soluble معرفی می کند. رد گزینه ها: ب توصیف metals است. ج توصیف ceramics است. د توصیف polymers است.',
                    ],
                    [
                        'question' => 'چرا افزودن polymer به ceramic particles در composite از نظر handling مفید است؟',
                        'options' => [
                            'چون polymer ذرات را bind می کند و استفاده از ماده به شکل paste را ممکن می سازد.',
                            'چون polymer flaws طبیعی ceramic را حذف می کند.',
                            'چون polymer valence electrons آزاد ایجاد می کند.',
                            'چون polymer firing در دمای بالا را جایگزین polymerization می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: ceramic particles به خودی خود به هم adhere نمی کنند؛ polymer آن ها را bind می کند و composite به صورت paste قابل استفاده می شود. رد گزینه ها: ب polymer flawهای ceramic را حذف نمی کند. ج valence electrons آزاد ویژگی فلزات است. د firing جایگزین polymerization composite نمی شود.',
                    ],
                    [
                        'question' => 'چرا استفاده از polymer به صورت مستقل برای نقش dental composite کافی دانسته نشده است؟',
                        'options' => [
                            'stiffness و stability کافی ایجاد نمی کند و این ها از ceramic particles تامین می شوند.',
                            'بیش از اندازه opaque است و قابل تغییر shade نیست.',
                            'با کاربرد دندان پزشکی مطرح نمی شود.',
                            'از نظر متن سطحی با luster فلزی ایجاد می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: polymer alone stiffness و stability کافی ایجاد نمی کند؛ ceramic particles این خواص را به composite می دهند. رد گزینه ها: ب متن چنین دلیلی نمی آورد. ج polymers در dentistry کاربرد گسترده دارند. د luster فلزی ویژگی polymers نیست.',
                    ],
                    [
                        'question' => 'درباره Bis-GMA در dental resin composites، کدام برداشت با فصل سازگار است؟',
                        'options' => [
                            'در استفاده در dental resin composites significant health risks نشان نداده است.',
                            'یک porcelain از kaolin، quartz و feldspar است.',
                            'عامل خوردگی gold و platinum در محلول است.',
                            'واحد سلولی آن BCC با atom مرکزی است.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: متن درباره Bis-GMA می گوید در dental resin composites significant health risks نشان نداده است. رد گزینه ها: ب porcelain از kaolin، quartz و feldspar است. ج gold/platinum corrosion به metallic force و solvation مربوط است. د BCC واحد سلولی فلزی است.',
                    ],
                    [
                        'question' => 'تفاوت composites با alloys در سطح microscopic چگونه توضیح داده شده است؟',
                        'options' => [
                            'در composites اجزای منفرد قابل مشاهده اند؛ در alloys چنین توصیفی برای متن مطرح نیست.',
                            'در alloys پلیمر matrix و ceramic particles تقویت کننده اند.',
                            'composites single-crystal هستند و alloys cross-linked networks.',
                            'composites با oxidation-reduction شکل می گیرند و alloys با polymerization.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فصل می گوید composites از alloys متفاوت اند، چون در microscopic level اجزای individual composite قابل مشاهده اند. رد گزینه ها: ب نقش polymer matrix و ceramic particles مربوط به composites است نه alloys. ج برعکس و نامعتبر است. د composites و alloys با چنین فرایندهایی تعریف نشده اند.',
                    ],
                    [
                        'question' => 'مثال concrete در فصل برای توضیح composites چه اجزایی دارد؟',
                        'options' => [
                            'sand، gravel و portland cement',
                            'kaolin، quartz و feldspar',
                            'gold، palladium و cobalt',
                            'methyl، ethyl و propyl methacrylate',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: concrete در فصل composite of sand، gravel و portland cement معرفی شده است. رد گزینه ها: ب ترکیب porcelain است. ج فلزات/آلیاژها هستند. د نمونه terpolymer methacrylate است.',
                    ],
                    [
                        'question' => 'در analogy concrete، نقش cement به کدام نقش در dental resin composite شبیه است؟',
                        'options' => [
                            'binder برای particles',
                            'crystalline flaw initiator',
                            'valence electron donor',
                            'cross-linking monomer',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: در concrete، cement binder برای sand و gravel particles است؛ در dental resin composite نیز polymer matrix binder برای ceramic particles است. رد گزینه ها: ب به flaws مربوط است. ج به فلزات مربوط است. د به thermoset/cross-linking مربوط است.',
                    ],
                    [
                        'question' => 'اگر در concrete سطحی، binder با استفاده شسته شود و particles به خوبی احاطه نشوند، نتیجه مشابه کدام ضعف مفهومی است؟',
                        'options' => [
                            'particle dislodgement و void در surface',
                            'افزایش thermoplastic softening',
                            'تشکیل FCC array',
                            'کاهش degree of polymerization',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فصل توضیح می دهد که با شسته شدن cement از سطح concrete، particles incompletely surrounded شده و dislodged می شوند و void باقی می ماند. رد گزینه ها: ب به thermoplastics مربوط است. ج مربوط به metals است. د degree of polymerization مربوط به polymers است.',
                    ],
                    [
                        'question' => 'در dental resin composites، coupling agents چه وظیفه ای دارند؟',
                        'options' => [
                            'افزایش adhesion بین ceramic particles و polymer matrix برای wear resistance و surface integrity بهتر',
                            'کاهش valence electron mobility در metallic lattice',
                            'ایجاد thermal shock هنگام cooling porcelain',
                            'تبدیل thermoplastic به random copolymer بدون cross-linking',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: coupling agents adhesion بین ceramic particles و polymer matrix را افزایش می دهند و wear resistance و long-term surface integrity را بهتر می کنند. رد گزینه ها: ب metallic lattice نیست. ج thermal shock porcelain نیست. د هدف coupling agent تبدیل thermoplastic یا تغییر copolymer نیست.',
                    ],
                    [
                        'question' => 'در polymerization resin composites، contraction volumetric کجا رخ می دهد؟',
                        'options' => [
                            'در polymer matrix',
                            'در crystalline phase ceramic',
                            'در metal framework',
                            'در portland cement aggregate',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: متن می گوید در polymerization resin composites، volumetric contraction of the polymer matrix رخ می دهد. رد گزینه ها: ب crystalline phase ceramic محل contraction matrix نیست. ج metal framework مربوط به porcelain/metal restoration است. د portland cement در analogy concrete است.',
                    ],
                    [
                        'question' => 'چرا polymerization contraction در restoration می تواند stress ایجاد کند؟',
                        'options' => [
                            'contraction strain با افزایش elastic modulus هنگام cure همراه می شود و مرز ترمیم به enamel و dentin چسبیده و constrained است.',
                            'contraction باعث می شود valence electrons آزاد از lattice خارج شوند.',
                            'contraction surface flaws را به طور کامل حذف می کند.',
                            'contraction ناشی از thermal mismatch بین leucite و glassy matrix است.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: contraction strain با افزایش elastic modulus during cure همراه می شود و چون periphery ترمیم به enamel و dentin walls چسبیده و constrained است، stress در composite ایجاد می شود. رد گزینه ها: ب خوردگی فلزات را توصیف می کند. ج contraction flaws را حذف نمی کند. د thermal mismatch leucite/glass مربوط به feldspathic porcelain است.',
                    ],
                    [
                        'question' => 'کدام روش کلی در فصل برای کاهش residual stress در resin composites ذکر شده است؟',
                        'options' => [
                            'توسعه polymers با reduced shrinkage و modifications to clinical placement technique',
                            'افزایش عمدی surface cracks برای پخش تنش',
                            'جایگزینی ceramic particles با valence electrons آزاد',
                            'کاهش molecular weight distribution تا حد حذف mer units',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن: فصل روش های کاهش residual stress را توسعه polymers با reduced shrinkage during cure و modifications to clinical placement technique معرفی می کند. رد گزینه ها: ب افزایش cracks راهکار کاهش stress نیست. ج جایگزینی particles با valence electrons مفهوم فلزی و نامعتبر است. د حذف mer units با polymer تعریفاً ناسازگار است.',
                    ],
                ],
            ],
            [
                'slug' => '8-1',
                'path' => '/exams/craig-dental-materials/6-10/8-1/',
                'questionCount' => 40,
                'label' => 'فصل ۸ - قسمت اول',
                'title' => 'آزمون فصل ۸ - قسمت اول',
                'subtitle' => '۴۰ سؤال چهارگزینه‌ای از مجموعه کریگ.',
                'description' => 'مرور ۴۰ سؤال از مجموعه کریگ.',
                'eyebrow' => 'کریگ | فصول ۶ تا ۱۰ | فصل ۸ - قسمت اول',
                'backHref' => '/exams/craig-dental-materials/6-10/',
                'backLabel' => 'بازگشت به فصول ۶ تا ۱۰ کریگ',
                'autoAdvance' => true,
                'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'siteSubtitle' => 'آزمون‌ها',
                'siteBadge' => 'آزمون رفرنسی',
                'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'questions' => [
                    [
                        'question' => 'در آماده سازی یک مولر تازه رویش یافته، ماده ای انتخاب شده که ویسکوزیته آن کمی بالاتر از حد معمول است و اپراتور انتظار نفوذ عمیق در شیارهای باریک دارد. بر اساس فصل، کدام پیامد محتمل تر است؟',
                        'options' => [
                            'تشکیل tags عمیق تر به دلیل کنترل بهتر جریان ماده',
                            'نفوذ ناکامل به pit و fissure و افزایش احتمال void در کف شیار',
                            'کاهش نیاز به etching به دلیل ماندگاری مکانیکی بیشتر',
                            'افزایش معنی دار آزادسازی فلوراید در ۲۴ ساعت اول',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: در منبع تاکید شده sealant خیلی غلیظ و ویسکوز به کف pit و fissure و حتی داخل enamel etched به خوبی نفوذ نمی کند؛ در نتیجه پرشدن ناکامل و void محتمل تر است. دلیل رد گزینه های غلط: - گزینه الف: کنترل جریان، جایگزین نفوذپذیری پایین نیست و tags عمیق تر نمی سازد. - گزینه ج: etching برای ایجاد سطح میکروگیر لازم است و با ویسکوزیته بالاتر حذف نمی شود. - گزینه د: آزادسازی فلوراید به فرمولاسیون ماده مربوط است، نه صرفاً افزایش ویسکوزیته.',
                    ],
                    [
                        'question' => 'در بیماری با ریسک بالای caries، ترمیم cervical با ماده ای نیاز است که از compomer و composite فلوراید بیشتری آزاد کند و از نظر flexural strength از glass ionomer معمولی بالاتر باشد. مناسب ترین انتخاب کدام است؟',
                        'options' => [
                            'Resin-modified glass ionomer',
                            'Conventional glass ionomer',
                            'Flowable composite',
                            'Calcium hydroxide liner',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: RMGI طبق فصل فلورایدی بیشتر از compomer و composite و تقریباً مشابه glass ionomer آزاد می کند و flexural strength آن تقریباً دو برابر glass ionomer معمولی گزارش شده است. دلیل رد گزینه های غلط: - گزینه ب: Conventional glass ionomer فلوراید بالایی دارد، اما flexural strength آن از RMGI کمتر است. - گزینه ج: Flowable composite به عنوان sealant/ترمیم پیشگیرانه مطرح است و در فصل چنین الگوی آزادسازی فلورایدی برای آن بیان نشده است. - گزینه د: Calcium hydroxide liner ماده liner/pulp capping است و برای چنین ترمیم cervical با این ویژگی ها معرفی نشده است.',
                    ],
                    [
                        'question' => 'پس از etching شیار اکلوزال با phosphoric acid، سطح به جای ظاهر سفید و dull، نامنظم و بخشی از آن براق مانده است. اقدام منطبق با متن چیست؟',
                        'options' => [
                            'اعمال bonding agent بدون تکرار etching',
                            'افزایش ضخامت sealant برای جبران سطح نامنظم',
                            'etching اضافی به مدت ۳۰ ثانیه',
                            'خشک کردن طولانی تر همراه با مالش سطح',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن گزینه ج: در صورت یکنواخت نبودن ظاهر etched enamel، منبع انجام ۳۰ ثانیه etching اضافی را توصیه می کند. دلیل رد گزینه های غلط: - گزینه الف: bonding agent قبل از sealant می تواند retention را بهتر کند، اما جایگزین etching ناکافی نیست. - گزینه ب: افزایش ضخامت، نقص آماده سازی سطح و خطر نشت/عدم گیر را اصلاح نمی کند. - گزینه د: مالش سطح حین etching و drying می تواند roughness ایجادشده را از بین ببرد.',
                    ],
                    [
                        'question' => 'در سالمند دارای caries فعال، مایعی بی رنگ برای arrest ضایعه به کار رفته، اما تیم درمان نگران سیاه شدن دندان و لباس بیمار است. کدام ویژگی با این ماده سازگارتر است؟',
                        'options' => [
                            'محلول حدود ۳۰٪ silver diamine fluoride با pH نزدیک ۱۰',
                            'وارنیش ۵٪ sodium fluoride با ۲۲۶۰۰ ppm فلوراید',
                            'خمیر CPP-ACP با کمپلکس casein phosphopeptide',
                            'bioactive glass از نوع calcium sodium phosphosilicate',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: SDF حدود ۳۰٪ در آب، با حدود ۲۵٪ نقره و ۵٪ فلوراید و pH تقریباً ۱۰ معرفی شده و مشکل اصلی آن staining دندان، پوست یا لباس است. دلیل رد گزینه های غلط: - گزینه ب: وارنیش فلوراید برای تماس طولانی فلوراید استفاده می شود، اما ویژگی سیاه کردن قوی در متن برای آن ذکر نشده است. - گزینه ج: CPP-ACP برای remineralization و تثبیت کلسیم/فسفات مطرح است، نه اثر سیاه کنندگی ناشی از نقره. - گزینه د: bioactive glass در dentifrice می تواند tubuleها را occlude کند، اما staining مشابه SDF در متن ندارد.',
                    ],
                    [
                        'question' => 'بعد از light-curing یک sealant، سطح ماده tacky باقی مانده است. بر اساس متن، کدام روش برای حذف این لایه موثرتر است؟',
                        'options' => [
                            'شست وشوی ساده با آب',
                            'پاک کردن با گاز خشک',
                            'استفاده از slurry پومیس با پنبه یا prophylaxis cup',
                            'اعمال لایه دوم sealant بدون تمیزکاری',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن گزینه ج: لایه air-inhibited resin بعد از curing با slurry پومیس روی cotton pellet یا prophylaxis cup بهتر برداشته می شود و این روش از wiping یا rinsing موثرتر است. دلیل رد گزینه های غلط: - گزینه الف: شست وشوی ساده در متن کمتر موثر از پومیس ذکر شده است. - گزینه ب: پاک کردن خشک نیز در حد روش پیشنهادی نیست. - گزینه د: اعمال لایه دوم روی لایه tacky مشکل حذف سطح uncured را برطرف نمی کند.',
                    ],
                    [
                        'question' => 'در مقایسه با mouthwash فلوراید، مزیت اصلی fluoride varnish از دید فصل چیست؟',
                        'options' => [
                            'تبدیل مستقیم enamel به glass ionomer',
                            'افزایش زمان تماس ماده فعال با سطح دندان از ثانیه ها به ساعت ها',
                            'حذف نیاز به prophylaxis پیش از استفاده',
                            'ایجاد polymer tags در enamel etched',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: متن مزیت varnish را تماس طولانی تر fluoride با سطح دندان می داند؛ به جای چند ثانیه در mouthwash، ممکن است ساعت ها قبل از ساییده شدن روی دندان باقی بماند. دلیل رد گزینه های غلط: - گزینه الف: وارنیش دندان را به glass ionomer تبدیل نمی کند؛ مکانیسم آن رسوب CaF و سپس remineralization به fluorapatite است. - گزینه ج: وارنیش ها معمولاً پس از prophylaxis استفاده می شوند. - گزینه د: ایجاد polymer tags مربوط به sealant رزینی و enamel etched است، نه fluoride varnish.',
                    ],
                    [
                        'question' => 'در glass ionomer معمولی، strengthening نهایی cross-linking عمدتاً به کدام تغییر نسبت داده شده است؟',
                        'options' => [
                            'ورود aluminum ion exchange پس از initial set کلسیمی',
                            'polymerization نوری HEMA در ۱۰ تا ۲۰ ثانیه',
                            'واکنش calcium hydroxide با salicylate',
                            'رسوب calcium fluoride روی سطح enamel',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در فصل، initial set بیشتر با calcium در matrix ژلی cross-linked رخ می دهد و strengthening نهایی با aluminum ion exchange تقویت می شود. دلیل رد گزینه های غلط: - گزینه ب: HEMA و light-curing مربوط به RMGI است، نه glass ionomer معمولی. - گزینه ج: واکنش calcium hydroxide با salicylate مربوط به calcium hydroxide liner است. - گزینه د: رسوب calcium fluoride مکانیسم fluoride varnish است، نه واکنش setting glass ionomer.',
                    ],
                    [
                        'question' => 'در یک cavity عمیق، دندانپزشک قصد linerگذاری عمومی روی کل کف پالپی را دارد و علاوه بر fluoride release، استحکام مکانیکی بهتر می خواهد. مطابق فصل، کدام ماده بر calcium hydroxide ارجح است؟',
                        'options' => [
                            'RMGI liner',
                            'Self-cured calcium hydroxide liner',
                            'MTA',
                            'Silver diamine fluoride',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید calcium hydroxide liner بیشتر برای direct pulp capping و نواحی عمیق خاص مناسب است، نه lining عمومی؛ RMGI liners برای lining عمومی بهترند چون fluoride release، solubility کمتر و خواص مکانیکی برتر دارند. دلیل رد گزینه های غلط: - گزینه ب: خود calcium hydroxide به دلیل modulus پایین و solubility قابل توجه برای lining عمومی مناسب تر معرفی نشده است. - گزینه ج: MTA برای pulp capping و root-end filling مطرح است و در متن به عنوان liner عمومی حفره توصیه نشده است. - گزینه د: SDF برای arrest caries/desensitizer است، نه liner ساختاری کل حفره.',
                    ],
                    [
                        'question' => 'در مطالعه ۴ ساله ای که sealant دارای فلوراید با نوع بدون فلوراید مقایسه شد، تفسیر درست کدام است؟',
                        'options' => [
                            'نوع فلورایددار retention بالاتر و caries کمتر داشت',
                            'نوع بدون فلوراید retention کمتر ولی caries مشابه داشت',
                            'نوع فلورایددار retention کمی کمتر داشت، اما caries در دو گروه ۱۰٪ بود',
                            'هر دو نوع retention کامل برابر و caries صفر داشتند',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن گزینه ج: در متن، retention برای fluoride-releasing sealant برابر ۹۱٪ و برای nonfluoride sealant برابر ۹۵٪ بود، اما caries incidence در هر دو گروه ۱۰٪ گزارش شد. دلیل رد گزینه های غلط: - گزینه الف: فلورایددار retention بالاتر نداشت و کاهش caries نسبت به گروه مقابل نشان داده نشد. - گزینه ب: بدون فلوراید retention کمتر نداشت؛ retention آن ۹۵٪ بود. - گزینه د: نه retention کامل برابر بود و نه caries صفر گزارش شد.',
                    ],
                    [
                        'question' => 'در چارچوب caries balance مطرح شده توسط Featherstone، کدام مجموعه با عوامل protective همخوان تر است؟',
                        'options' => [
                            'acid-producing bacteria، مصرف مکرر carbohydrate، کاهش salivary flow',
                            'normal salivary flow/components، fluoride، antibacterials',
                            'fluoride، sucrose، below-normal salivary function',
                            'calcium hydroxide، bismuth oxide، tricalcium aluminate',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: سه عامل protective در متن شامل جریان و اجزای طبیعی بزاق، فلوراید و antibacterials است. دلیل رد گزینه های غلط: - گزینه الف: این ها سه عامل pathological در همان مفهوم اند. - گزینه ج: sucrose/کربوهیدرات قابل تخمیر و کاهش عملکرد بزاق protective نیستند. - گزینه د: این مجموعه بیشتر به اجزای MTA/liner نزدیک است و عوامل protective caries balance نیست.',
                    ],
                    [
                        'question' => 'اگر هدف ساخت sealant رزینی با ویسکوزیته پایین از Bis-GMA باشد، نقش triethylene glycol dimethacrylate در متن چیست؟',
                        'options' => [
                            'diluent برای تبدیل رزین ویسکوز به resin flowableتر',
                            'photoinitiator برای آغاز curing در حضور tertiary amine',
                            'filler برای افزایش stiffness و wear resistance',
                            'عامل chelation با calcium سطح دندان',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: Bis-GMA به خودی خود ویسکوز است و با diluent مانند triethylene glycol dimethacrylate مخلوط می شود تا resin با ویسکوزیته پایین و قابل جریان ایجاد شود. دلیل رد گزینه های غلط: - گزینه ب: شروع light-cure در متن با photosensitive diketone و tertiary amine توضیح داده شده است. - گزینه ج: افزایش stiffness و wear resistance با fillers مانند fumed silica یا silanated glass انجام می شود. - گزینه د: chelation با calcium سطح دندان مربوط به glass ionomer و گروه های COO- است.',
                    ],
                    [
                        'question' => 'در مقایسه MTA با calcium hydroxide برای pulp capping، کدام گزاره دقیق تر است؟',
                        'options' => [
                            'MTA سریع تر سخت می شود و ارزان تر است',
                            'MTA کندتر سخت می شود، گران تر است و outcome بهتری گزارش شده است',
                            'MTA فاقد calcium hydroxide در محصولات واکنش است',
                            'MTA فقط برای وارنیش فلوراید استفاده می شود',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: متن MTA را کندتر از calcium hydroxide می داند، با hardening در حد ساعت ها تا روزها، گران تر است و مطالعات outcome ایده آل تری برای pulp capping نشان داده اند. دلیل رد گزینه های غلط: - گزینه الف: متن خلاف آن را می گوید: کندتر و گران تر است. - گزینه ج: محصول اصلی واکنش MTA با آب calcium hydroxide است. - گزینه د: MTA برای pulp capping و endodontic root-end filling ذکر شده، نه وارنیش فلوراید.',
                    ],
                    [
                        'question' => 'افزودن ceramic یا glass filler تا حدود ۴۰٪ وزنی به sealant رزینی، بیشترین بهبود چشمگیر را در کدام ویژگی ایجاد می کند؟',
                        'options' => [
                            'modulus of elasticity',
                            'fluoride rechargeability',
                            'زمان تغییر رنگ photosensitive',
                            'عمق tags پس از etching',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: منبع می گوید با افزودن filler تا ۴۰٪، بیشتر خواص بهبود می یابند و dramatic improvement مربوط به modulus of elasticity است. دلیل رد گزینه های غلط: - گزینه ب: rechargeability بیشتر در glass ionomer/RMGI مطرح است. - گزینه ج: تغییر رنگ photosensitive مربوط به pigments در sealantهای color-reversible است، نه filler. - گزینه د: عمق tags به etching و نفوذ resin مربوط است و در متن افزایش آن با filler ذکر نشده است.',
                    ],
                    [
                        'question' => 'در calcium hydroxide liner خودسخت شونده، واکنش اصلی setting با کدام ترکیب نهایی توضیح داده شده است؟',
                        'options' => [
                            'تشکیل amorphous calcium disalicylate از calcium hydroxide و salicylate',
                            'تشکیل matrix ژلی با salt bridges بین Al3+ و COO-',
                            'polymerization نوری 2-hydroxyethyl methacrylate',
                            'رسوب fluorapatite پس از رسوب calcium fluoride',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: اجزای مسیول setting در calcium hydroxide liner، calcium hydroxide و salicylate هستند که amorphous calcium disalicylate تشکیل می دهند. دلیل رد گزینه های غلط: - گزینه ب: salt bridges بین Al/Ca و COO- مربوط به glass ionomer است. - گزینه ج: HEMA polymerization مربوط به RMGI است. - گزینه د: رسوب CaF و تبدیل به fluorapatite مکانیسم varnish است.',
                    ],
                    [
                        'question' => 'در preventive restoration با flowable composite، چرا low viscosity مزیت محسوب می شود؟',
                        'options' => [
                            'به material اجازه می دهد به fissureهای مجاور نیز به صورت sealant امتداد یابد',
                            'باعث حذف کامل مرحله curing می شود',
                            'سبب کاهش filler content نسبت به resin sealant می شود',
                            'جایگزین کنترل air trapping می شود',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: طبق متن، low viscosity در flowable composites هنگام استفاده به صورت preventive restoration باعث می شود restoration به fissureهای مجاور به عنوان sealant امتداد یابد. دلیل رد گزینه های غلط: - گزینه ب: flowable composite همچنان نیازمند curing مناسب است. - گزینه ج: متن می گوید filler content آن ها از بیشتر resin sealants بالاتر است. - گزینه د: حتی در flowable composites نیز trapping هوا باید اجتناب شود.',
                    ],
                    [
                        'question' => 'کدام فرمول با یکی از varnishهای فلورایدی معرفی شده در فصل همخوان است؟',
                        'options' => [
                            '۵٪ sodium fluoride با ۲.۲۶٪ F− یا ۲۲۶۰۰ ppm',
                            '۳۰٪ silver diamine fluoride با ۲۵٪ F− و ۵٪ silver',
                            '۱۵٪ polyacrylic acid با ۱۰۰۰ ppm F−',
                            '۱.۱٪ neutral sodium fluoride gel به عنوان ماتریکس setting',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: فصل دو نوع varnish را ذکر می کند؛ یکی ۵٪ sodium fluoride با ۲.۲۶٪ F− یا ۲۲۶۰۰ ppm است. دلیل رد گزینه های غلط: - گزینه ب: در SDF حدود ۲۵٪ silver و ۵٪ fluoride مطرح است، نه وارونه؛ SDF varnish ذکرشده نیست. - گزینه ج: ۱۵٪ تا ۲۵٪ polyacrylic acid برای conditioning glass ionomer ذکر شده، نه varnish فلورایدی. - گزینه د: ۱.۱٪ neutral sodium fluoride gel در شکل recharge مواد ionomer مطرح شده، نه ماتریکس setting وارنیش.',
                    ],
                    [
                        'question' => 'برای cervical restoration در بزرگسال با esthetics نه چندان حیاتی و caries risk بالا، کدام دلیل از متن استفاده از conventional glass ionomer را تقویت می کند؟',
                        'options' => [
                            'slow release فلوراید و توصیه برای بیماران high caries risk',
                            'عدم نیاز به محافظت سطح از بزاق در initial set',
                            'bond strength بالاتر از resin composite به dentin',
                            'سطح نهایی کاملاً صاف و بدون mismatch رنگی در ۴ سال',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: glass ionomers به دلیل documented slow release فلوراید در cervical restorations به ویژه وقتی esthetics اولویت اصلی نیست و برای high caries risk توصیه شده اند. دلیل رد گزینه های غلط: - گزینه ب: سطح ترمیم جدید باید در initial set با varnish یا light-cured resin در برابر saliva محافظت شود. - گزینه ج: متن می گوید bond strength آن ها به dentin پایین تر از resin composite است. - گزینه د: در داده ۴ ساله، rough surface و برخی shade mismatch دیده شد.',
                    ],
                    [
                        'question' => 'در ضایعات subsurface enamel، فناوری CPP-ACP چگونه در متن توضیح داده شده است؟',
                        'options' => [
                            'پایدارکردن سطوح بالای calcium و phosphate ions برای remineralization',
                            'ایجاد bismuth oxide radiopacity برای root-end filling',
                            'کاهش pH به حدود ۵ برای حل پلاک',
                            'ایجاد لایه uncured resin پس از light curing',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: CPP-ACP با stabilizing مقادیر بالای calcium و phosphate ions در remineralizing enamel subsurface lesions موثر توصیف شده است. دلیل رد گزینه های غلط: - گزینه ب: bismuth oxide جزء MTA برای radiopacity است. - گزینه ج: متن چنین کاهش pH برای CPP-ACP ذکر نمی کند. - گزینه د: لایه uncured resin مربوط به air inhibition در sealantهای رزینی است.',
                    ],
                    [
                        'question' => 'در معاینه بلافاصله پس از curing، بخشی از sealant coverage ناکافی دارد. طبق متن، ترمیم نقص چگونه انجام می شود؟',
                        'options' => [
                            'تکرار کل procedure شامل acid etch و افزودن sealant تازه فقط روی ناحیه ناکافی',
                            'افزودن sealant تازه بدون etching برای جلوگیری از overcoverage',
                            'حذف کامل sealant و تعویض با glass ionomer در همه موارد',
                            'صرفاً adjustment اکلوزال، چون coverage در retention موثر نیست',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: برای void یا insufficient coverage، منبع تکرار entire application procedure شامل acid etch و اعمال sealant تازه روی همان نواحی را توصیه می کند. دلیل رد گزینه های غلط: - گزینه ب: بدون etching، bonding مناسب ناحیه ترمیم شده تضمین نمی شود. - گزینه ج: تعویض کامل با glass ionomer توصیه عمومی برای نقص کوچک نیست. - گزینه د: coverage کامل pits و fissures برای seal و retention مهم است؛ adjustment occlusion جایگزین آن نیست.',
                    ],
                    [
                        'question' => 'چرا در RMGIهای encapsulated، mechanical mixing از دید متن مزیت دارد؟',
                        'options' => [
                            'mix یکنواخت تری با voidهای بزرگ کمتر نسبت به hand spatulation ایجاد می کند',
                            'زمان hardening را از ساعت ها به روزها افزایش می دهد',
                            'نیاز به powder-to-liquid ratio را حذف می کند',
                            'fluoride release را به صفر می رساند تا solubility کم شود',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن مزیت mechanical mixing کپسول ها را mix یکنواخت و کاهش voidهای بزرگ نسبت به hand spatulation می داند. دلیل رد گزینه های غلط: - گزینه ب: ساعت ها تا روزها مربوط به MTA است، نه RMGI. - گزینه ج: نسبت powder/liquid همچنان برای حفظ خواص و موفقیت بالینی critical است. - گزینه د: RMGIها به دلیل fluoride release یکی از مواد مهم اند و release به صفر هدف نیست.',
                    ],
                    [
                        'question' => 'برای پیگیری recall، دندانپزشک sealantی می خواهد که هنگام نوردهی dental curing light به طور موقت سبز یا صورتی شود و بعد دوباره قابل بررسی باشد. کدام ویژگی متن را بازتاب می دهد؟',
                        'options' => [
                            'photosensitive pigments با تغییر رنگ ۵ تا ۱۰ دقیقه ای و امکان تکرار در recall',
                            'opaque filler با release فلوراید ثابت و مادام العمر',
                            'glass ionomer با latent caries protection پس از loss',
                            'resin composite با polymerization بدون air-inhibition',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: color-reversible photosensitive sealants دارای pigments معمولاً بی رنگ اند که با curing light سبز یا صورتی می شوند؛ تغییر رنگ ۵ تا ۱۰ دقیقه طول می کشد و در recall قابل تکرار است. دلیل رد گزینه های غلط: - گزینه ب: opaque/tinted sealants برای دید بهتر در recall هستند، اما تغییر رنگ photosensitive ۵ تا ۱۰ دقیقه ای ویژگی دیگری است. - گزینه ج: latent caries protection پس از loss مربوط به glass ionomer sealants است. - گزینه د: air-inhibition برای resin curing یک مسیله ذکرشده است و حذف کامل آن در متن نیامده است.',
                    ],
                    [
                        'question' => 'در بیمار با حساسیت دنتینی و نیاز به dentifrice فعال، کدام مکانیسم به bioactive glass فصل نزدیک تر است؟',
                        'options' => [
                            'deposit شدن روی dentin و occlude مکانیکی dentinal tubules',
                            'تشکیل tags ۲۵ تا ۵۰ میکرومتری در enamel etched',
                            'salt bridge بین Al3+ و Ca2+ با COO-',
                            'رسوب bismuth oxide برای radiopacity',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: bioactive glass از نوع calcium sodium phosphosilicate در dentifrice روی dentin رسوب می کند و tubules را به طور مکانیکی occlude می نماید. دلیل رد گزینه های غلط: - گزینه ب: tags در enamel etched مربوط به resin sealant است. - گزینه ج: salt bridge مربوط به setting glass ionomer است. - گزینه د: bismuth oxide جزء radiopaque در MTA است.',
                    ],
                    [
                        'question' => 'در مطالعات sealant، کدام جمع بندی با متن سازگارتر است؟',
                        'options' => [
                            'retention ماده با caries protection رابطه مستقیم دارد',
                            'fluoride release به تنهایی retention را تعیین می کند',
                            'self-cured بودن همیشه retention کامل طولانی مدت می دهد',
                            'رنگ clear نسبت به opaque caries protection بالاتری دارد',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: تقریباً همه مطالعات در متن نشان می دهند بین sealant retention و caries protection رابطه مستقیم وجود دارد. دلیل رد گزینه های غلط: - گزینه ب: در مطالعه مقایسه fluoride/nonfluoride، caries برابر بود و retention به فلوراید وابسته تفسیر نشد. - گزینه ج: مطالعه ۱۵ ساله self-cured unfilled retention کامل ۲۷.۶٪ و partial ۳۵.۴٪ نشان داد، نه retention کامل. - گزینه د: رنگ ماده برای ظاهر یا recall مطرح است و در متن برتری caries protection برای clear نسبت به opaque ذکر نشده است.',
                    ],
                    [
                        'question' => 'در ارزیابی self-cured calcium hydroxide liner، چرا نباید برای support عمده ترمیم به آن تکیه کرد؟',
                        'options' => [
                            'tensile strength، compressive strength و elastic modulus پایینی دارد',
                            'fluoride release آن از glass ionomer بیشتر است',
                            'polyacrylic acid باعث bond strength ۲ تا ۳ MPa می شود',
                            'در برابر saliva initial set نیاز به varnish ندارد',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن calcium hydroxide liners خودسخت شونده را دارای tensile/compressive strength و elastic modulus پایین نسبت به high-strength bases می داند؛ بنابراین کاربرد آن ها به نواحی غیرحیاتی برای support محدود است. دلیل رد گزینه های غلط: - گزینه ب: fluoride release بالاتر برای آن در متن مطرح نیست. - گزینه ج: polyacrylic acid و bond strength ۲ تا ۳ MPa مربوط به glass ionomer است. - گزینه د: این گزاره درباره GI/RMGI نیست و مسیله اصلی CaOH در متن خواص مکانیکی پایین و solubility است.',
                    ],
                    [
                        'question' => 'در کودکان پرخطر بدون دسترسی به درمان definitive، روش ART طبق فصل کدام ترکیب اقدام و ماده را شامل می شود؟',
                        'options' => [
                            'بازکردن ضایعه، حذف decay نرم سطحی، و پر/سیل کردن با glass ionomer پرشده و fast-setting',
                            'etching با phosphoric acid و پوشاندن با sealant clear در همه سطوح صاف',
                            'استفاده از flowable composite برای پوشاندن تمام root caries',
                            'اعمال silver diamine fluoride و پوشاندن حتماً با ceramic',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: ART در متن شامل بازکردن lesion، حذف soft surface decay و filling/sealing با highly filled glass ionomer با زمان set سریع در محیط غنی از فلوراید است. دلیل رد گزینه های غلط: - گزینه ب: این توصیف بیشتر به sealant resin مربوط است و نه ART caries management. - گزینه ج: flowable composite برای applications مختلف و sealant/preventive restoration مطرح است، نه تعریف ART. - گزینه د: SDF در نیمه دوم برای arrest caries مطرح است، اما تعریف ART در فصل نیست.',
                    ],
                    [
                        'question' => 'منحنی های fluoride release از glass ionomer و RMGI طی ۳۰ روز چه الگویی را نشان می دهند؟',
                        'options' => [
                            'high release اولیه که پس از حدود ۱۰ روز به حدود ۱ ppm کاهش می یابد',
                            'آزادسازی صفر در روزهای اول و peak در انتهای ماه',
                            'افزایش خطی مداوم بدون tapering',
                            'رهاسازی فقط پس از recharge با neutral sodium fluoride',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن و شکل ها early period of high release را نشان می دهند که بعد از حدود ۱۰ روز به حدود ۱ ppm taper می کند. دلیل رد گزینه های غلط: - گزینه ب: الگوی peak انتهایی در متن ذکر نشده است. - گزینه ج: افزایش خطی مداوم با توصیف tapering سازگار نیست. - گزینه د: مواد قبل از recharge هم fluoride release دارند؛ recharge توان rerelease را تقویت می کند.',
                    ],
                    [
                        'question' => 'اگر هنگام sealant application، بزاق سطح etched enamel را آلوده کند، بهترین اقدام طبق فصل چیست؟',
                        'options' => [
                            'rinse سطح و reapply etchant',
                            'خشک کردن سطح و ادامه کار بدون etching',
                            'افزودن لایه ضخیم تر sealant برای راندن بزاق',
                            'استفاده از self-cured sealant به جای light-cured بدون آماده سازی دوباره',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در صورت salivary contamination در طول درمان، فصل rinse سطح و reapplication etchant را توصیه می کند. دلیل رد گزینه های غلط: - گزینه ب: خشک کردن صرف، contamination را برای tag formation و bonding جبران نمی کند. - گزینه ج: لایه ضخیم تر sealant مشکل bonding و contamination را حل نمی کند و ممکن است occlusion را مختل کند. - گزینه د: تعویض curing mode جایگزین آماده سازی دوباره سطح آلوده نیست.',
                    ],
                    [
                        'question' => 'مواد جدید calcium silicate–based برای lining دندان در فصل با کدام هدف اصلی معرفی شده اند؟',
                        'options' => [
                            'حفاظت pulp و امکان remineralization دنتین رویی',
                            'افزایش retention sealant در uncut enamel',
                            'کاهش release فلوراید varnish به کمتر از mouthwash',
                            'ایجاد رنگ صورتی موقت برای recall',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید مواد calcium silicate–based جدید با اهداف اصلی protection of pulp و potential remineralization of overlying dentin توسعه یافته اند. دلیل رد گزینه های غلط: - گزینه ب: retention sealant در uncut enamel به سیستم های etch/prime و bonding مربوط است. - گزینه ج: وارنیش فلوراید هدف تماس طولانی و release فلوراید دارد، نه کاهش آن زیر mouthwash. - گزینه د: رنگ صورتی موقت مربوط به color-reversible sealants است.',
                    ],
                    [
                        'question' => 'هنگام مخلوط کردن bulk glass ionomer معمولی، کدام توالی با متن سازگار است؟',
                        'options' => [
                            'ابتدا نصف powder برای قوام milky homogeneous، سپس بقیه powder؛ کل زمان mixing حدود ۳۰ تا ۴۰ ثانیه',
                            'ابتدا تمام powder با etchant، سپس liquid؛ curing نوری ۱۰ تا ۲۰ ثانیه',
                            'ابتدا liquid با salicylate، سپس calcium hydroxide؛ setting طی ۲.۵ تا ۵.۵ دقیقه',
                            'ابتدا monomer HEMA با primer، سپس powder؛ finishing بلافاصله پس از نوردهی',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در متن برای GI bulk، powder و liquid به مقدار مناسب روی pad گذاشته می شوند؛ نصف powder ابتدا برای قوام milky homogeneous اضافه می شود و سپس بقیه powder، با mixing time کل ۳۰ تا ۴۰ ثانیه. دلیل رد گزینه های غلط: - گزینه ب: این توالی با GI معمولی سازگار نیست و به sealant/RMGI نزدیک تر است. - گزینه ج: salicylate و calcium hydroxide مربوط به calcium hydroxide liner است. - گزینه د: HEMA و finishing سریع پس از light cure مربوط به RMGI است.',
                    ],
                    [
                        'question' => 'در sandwich technique توصیف شده در فصل، نقش RMGI کدام است؟',
                        'options' => [
                            'liner برای seal دنتین و fluoride release، سپس پوشاندن با resin composite',
                            'جایگزین کامل enamel etching برای pit and fissure sealant',
                            'ماده root-end filling با hardening چندروزه',
                            'محلول antimicrobal stain کننده برای arrest caries',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در sandwich technique، RMGI به عنوان liner برای seal dentin و بهره مندی از fluoride release استفاده می شود و سپس لایه سطحی resin composite باقی cavity را پر می کند. دلیل رد گزینه های غلط: - گزینه ب: این تکنیک جایگزین مرحله etching در sealantهای pit/fissure نیست. - گزینه ج: root-end filling با hardening ساعت ها تا روزها مربوط به MTA است. - گزینه د: محلول stain کننده arrest caries مربوط به SDF است.',
                    ],
                    [
                        'question' => 'کدام مجموعه شرایط با curing معمول light-cured sealant در فصل هماهنگ است؟',
                        'options' => [
                            'نوردهی ۱۰ تا ۲۰ ثانیه، نوک منبع نور در فاصله حدود ۱ تا ۲ mm از سطح',
                            'نوردهی ۵ دقیقه، فاصله ۱۰ mm، سپس finishing پس از ۲۴ ساعت',
                            'نوردهی پس از ۶ ماه recharge فلورایدی، بدون نیاز به applicator',
                            'نوردهی فقط برای opaque materials و نه thin layers',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: sealant با applicator روی pit/fissure گذاشته می شود و ۱۰ تا ۲۰ ثانیه با منبع نور در فاصله حدود ۱ تا ۲ mm cure می گردد؛ چون لایه نازک است، depth of cure کافی است. دلیل رد گزینه های غلط: - گزینه ب: ۵ دقیقه و فاصله ۱۰ mm برای light-cured sealant در متن نیامده است؛ finishing پس از ۲۴h مربوط به GI معمولی است. - گزینه ج: recharge فلورایدی به ionomerها مربوط است، نه زمان curing sealant. - گزینه د: لایه های نازک، حتی مواد opaque، با ۱۰ تا ۲۰ ثانیه exposure depth کافی دارند.',
                    ],
                    [
                        'question' => 'ویژگی pH و اثر درمانی calcium hydroxide liner در متن چگونه به هم مرتبط شده اند؟',
                        'options' => [
                            'pH حدود ۹.۲ تا ۱۱.۷ و free calcium hydroxide اضافی با تحریک secondary dentin و اثر antibacterial',
                            'pH اسیدی نزدیک ۴ و مهار کامل تشکیل calcium disalicylate',
                            'pH خنثی و آزادسازی پایدار فلوراید برای ۶ ماه',
                            'pH پایین تر از بزاق و ایجاد polymer tags در enamel',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: pH محصولات تجاری calcium hydroxide بین ۹.۲ و ۱۱.۷ ذکر شده و free calcium hydroxide اضافی، secondary dentin نزدیک pulp و فعالیت antibacterial را تحریک می کند. دلیل رد گزینه های غلط: - گزینه ب: متن pH اسیدی و مهار calcium disalicylate را ذکر نکرده است. - گزینه ج: release فلوراید ۶ ماهه برای یک RMGI-based varnish گزارش شده، نه CaOH liner. - گزینه د: polymer tags در enamel etched به sealant رزینی مربوط است.',
                    ],
                    [
                        'question' => 'چرا سطح glass ionomer معمولیِ تازه باید در initial set با varnish یا light-cured resin محافظت شود؟',
                        'options' => [
                            'برای محافظت از restoration در برابر saliva هنگام set اولیه',
                            'برای شروع واکنش HEMA polymerization',
                            'برای حذف کامل roughness و mismatch رنگی آینده',
                            'برای جلوگیری از release فلوراید از شیشه',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن توصیه می کند surfaces of new restorations در initial set با protective coating از varnish یا light-cured resin در برابر saliva محافظت شوند. دلیل رد گزینه های غلط: - گزینه ب: HEMA polymerization مربوط به RMGI است. - گزینه ج: محافظت اولیه تضمین حذف roughness یا shade mismatch در مطالعات ۴ ساله نیست. - گزینه د: هدف جلوگیری از fluoride release نیست؛ fluoride release اثر بالقوه anticariogenic دارد.',
                    ],
                    [
                        'question' => 'با وجود مزیت arrest ضایعات، چرا SDF اغلب نیاز دارد با ماده esthetic پوشانده شود؟',
                        'options' => [
                            'به دلیل اثر black-staining روی tooth structure',
                            'به دلیل سخت شدن چندروزه مشابه MTA',
                            'به دلیل کاهش شدید salivary flow',
                            'به دلیل ایجاد layer tacky resin پس از curing',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: مشکل اصلی SDF در متن staining ساختار دندان و اشیایی مانند پوست یا لباس است؛ بنابراین برای پوشاندن اثر سیاه، ماده esthetic لازم می شود. دلیل رد گزینه های غلط: - گزینه ب: hardening چندروزه مربوط به MTA است. - گزینه ج: کاهش salivary flow عامل pathological caries balance است، نه ویژگی SDF. - گزینه د: layer tacky مربوط به air inhibition sealant resin است.',
                    ],
                    [
                        'question' => 'بر اساس current evidence فصل، sealant در کدام وضعیت بیشترین اثربخشی را دارد؟',
                        'options' => [
                            'occlusal surfaces با pits و fissures مشخص و food-retentive در بیمار high risk',
                            'سطوح صاف قدامی با uptake فلوراید بالا و caries risk پایین',
                            'هر سطحی که قبلاً با glass ionomer rough شده باشد',
                            'root surfaces بدون exposure و بدون caries risk',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: sealants طبق متن روی occlusal surfaces با pits/fissures well defined و retentive به food، به ویژه در بیماران با ریسک بالای pit/fissure caries، موثرترین اند. دلیل رد گزینه های غلط: - گزینه ب: متن smooth surface caries را بیشتر تحت تاثیر fluoride کاهش یافته معرفی می کند، نه اولویت sealant. - گزینه ج: rough شدن با GI معیار انتخاب sealant نیست. - گزینه د: root surfaces exposed در بخش varnish/root caries مطرح می شود، نه pit/fissure sealant.',
                    ],
                    [
                        'question' => 'برای قرار دادن RMGI روی tooth structure، کدام آماده سازی معمول تر در متن آمده است؟',
                        'options' => [
                            'conditioning با polyacrylic acid یا primer پیش از placement',
                            'etching با ۳۵٪ تا ۴۰٪ phosphoric acid به مدت ۱۵ تا ۳۰ ثانیه به عنوان شرط الزامی RMGI',
                            'عدم استفاده از هرگونه conditioning چون bond agent لازم نیست',
                            'اعمال slurry پومیس پس از light curing برای ایجاد bond',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: RMGI بدون bonding agent به tooth structure bond می شود، اما معمولاً tooth با polyacrylic acid یا primer conditioning می شود. دلیل رد گزینه های غلط: - گزینه ب: phosphoric acid ۳۵٪ تا ۴۰٪ و ۱۵ تا ۳۰ ثانیه مربوط به etching enamel برای sealant است. - گزینه ج: عدم نیاز به bonding agent به معنی حذف conditioning نیست. - گزینه د: پومیس پس از curing برای برداشتن لایه air-inhibited sealant استفاده می شود، نه ایجاد bond RMGI.',
                    ],
                    [
                        'question' => 'چرا با وجود اثر چشمگیر فلوراید بر smooth surface caries، pits و fissures خلفی همچنان هدف sealant هستند؟',
                        'options' => [
                            'morphology نامنظم occlusal uptake فلوراید را دشوارتر کرده و food retention/brush difficulty ضایعه را آغاز می کند',
                            'فلوراید در دندان های خلفی به hydroxyapatite تبدیل نمی شود',
                            'سطوح occlusal به طور طبیعی فاقد enamel هستند',
                            'sealant بیشتر برای افزایش زیبایی smooth surfaces ساخته شده است',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: فصل توضیح می دهد pits و fissures روی occlusal surfaces خلفی به دلیل morphology نامنظم نسبت به fluoride uptake مقاوم ترند؛ food retention و سختی brushing می تواند آغاز caries را تسهیل کند. دلیل رد گزینه های غلط: - گزینه ب: متن چنین ناتوانی تبدیل فلوراید در خلفی ها را بیان نکرده است. - گزینه ج: سطوح occlusal enamel دارند؛ بحث بر سر irregular morphology است. - گزینه د: هدف sealant کاهش caries risk از راه صاف تر و پاک پذیرتر کردن سطح است، نه صرفاً زیبایی smooth surfaces.',
                    ],
                    [
                        'question' => 'مکانیسم fluoride varnish در فصل به کدام توالی نزدیک تر است؟',
                        'options' => [
                            'رسوب calcium fluoride روی tooth surface و تبدیل بعدی به fluorapatite طی remineralization',
                            'نفوذ resin به etched enamel و تشکیل polymer tags',
                            'واکنش tricalcium silicate با water و تشکیل calcium hydroxide',
                            'تثبیت high levels calcium/phosphate توسط casein phosphopeptide',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن مکانیسم varnish را مشابه mouthwash فلوراید می داند: CaF روی دندان رسوب می کند و بعد در remineralization به fluorapatite تبدیل می شود. دلیل رد گزینه های غلط: - گزینه ب: polymer tags مربوط به sealant resin است. - گزینه ج: واکنش silicate با آب و CaOH محصول MTA است. - گزینه د: تثبیت کلسیم/فسفات مربوط به CPP-ACP است.',
                    ],
                    [
                        'question' => 'single-step etching and priming systems در کاربرد sealant نسبت به کدام سطح bond ضعیف تری نشان می دهند و علت احتمالی چیست؟',
                        'options' => [
                            'uncut enamel، به دلیل reduced acidity',
                            'cut enamel walls، به دلیل افزایش filler content',
                            'beveled enamel، به دلیل fluoride recharge',
                            'dentin، به دلیل calcium disalicylate',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید single-step etching and priming systems ظاهراً bond ضعیف تری به uncut enamel نسبت به cut enamel walls و bevels دارند که احتمالاً به reduced acidity مربوط است. دلیل رد گزینه های غلط: - گزینه ب: مشکل اصلی در متن uncut enamel است، نه cut walls؛ filler content علت ذکرشده نیست. - گزینه ج: bevels در مقایسه bond بهتر از uncut enamel دارند و recharge فلورایدی مربوط نیست. - گزینه د: calcium disalicylate مربوط به setting calcium hydroxide liner است.',
                    ],
                    [
                        'question' => 'کدام روند درباره strength calcium hydroxide cements در ۲۴ ساعت اول با فصل سازگار است؟',
                        'options' => [
                            'با وجود setting در ۲.۵ تا ۵.۵ دقیقه، strength طی ۲۴ ساعت افزایش می یابد',
                            'پس از ۱۰ دقیقه strength به صفر می رسد چون solubility کامل است',
                            'strength بلافاصله پس از mixing از high-strength bases بیشتر می شود',
                            'strength فقط با recharge فلورایدی افزایش می یابد',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن setting time را ۲.۵ تا ۵.۵ دقیقه ذکر می کند، اما strength طی ۲۴ ساعت افزایش می یابد؛ مقادیر compressive strength نیز در ۲۴ ساعت بالاتر از ۱۰ دقیقه گزارش شده اند. دلیل رد گزینه های غلط: - گزینه ب: solubility قابل توجه است، اما strength پس از ۱۰ دقیقه صفر توصیف نشده است. - گزینه ج: CaOH liners نسبت به high-strength bases خواص مکانیکی پایین تری دارند. - گزینه د: recharge فلورایدی مربوط به ionomerهاست، نه افزایش strength CaOH.',
                    ],
                ],
            ],
            [
                'slug' => '8-2',
                'path' => '/exams/craig-dental-materials/6-10/8-2/',
                'questionCount' => 40,
                'label' => 'فصل ۸ - قسمت دوم',
                'title' => 'آزمون فصل ۸ - قسمت دوم',
                'subtitle' => '۴۰ سؤال چهارگزینه‌ای از مجموعه کریگ.',
                'description' => 'مرور ۴۰ سؤال از مجموعه کریگ.',
                'eyebrow' => 'کریگ | فصول ۶ تا ۱۰ | فصل ۸ - قسمت دوم',
                'backHref' => '/exams/craig-dental-materials/6-10/',
                'backLabel' => 'بازگشت به فصول ۶ تا ۱۰ کریگ',
                'autoAdvance' => true,
                'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'siteSubtitle' => 'آزمون‌ها',
                'siteBadge' => 'آزمون رفرنسی',
                'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'questions' => [
                    [
                        'question' => 'در glass ionomer معمولی، adhesion به سطح exposed tooth با کدام تعامل توضیح داده شده است؟',
                        'options' => [
                            'chelation بین polymer منفی و calcium سطح دندان',
                            'micromechanical bonding از طریق resin tags ۲۵ تا ۵۰ μm',
                            'polymerization نوری diketone/amine',
                            'occlusion مکانیکی tubules با bioactive glass',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: فصل توضیح می دهد تعامل یونی مشابه chelation بین polymer دارای بار منفی و calcium سطح دندان، adhesive bond در GI ایجاد می کند. دلیل رد گزینه های غلط: - گزینه ب: resin tags مربوط به sealant رزینی و etched enamel است. - گزینه ج: diketone/amine مربوط به آغاز polymerization در light-cured sealants است. - گزینه د: occlusion tubules مربوط به bioactive glass در dentifrice است.',
                    ],
                    [
                        'question' => 'در RMGI، چرا finishing می تواند نسبت به glass ionomer معمولی زودتر انجام شود؟',
                        'options' => [
                            'به دلیل set فوری پس از light-curing، finishing در ۵ تا ۱۰ دقیقه پس از initial set ممکن است',
                            'چون maturation آن مانند GI معمولی ۲۴ ساعت طول می کشد اما نیازی به صبر نیست',
                            'چون powder-to-liquid ratio روی خواص بلندمدت اثر ندارد',
                            'چون wet finishing رنگ و surface texture را تخریب می کند',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: RMGI برخلاف GI معمولی با light-curing بلافاصله set می شود و می توان ۵ تا ۱۰ دقیقه پس از initial set آن را finish کرد. دلیل رد گزینه های غلط: - گزینه ب: در GI معمولی به دلیل maturation کند، finishing معمولاً بعد از ۲۴ ساعت توصیه می شود؛ RMGI متفاوت است. - گزینه ج: نسبت powder-to-liquid در RMGI برای خواص بلندمدت critical است. - گزینه د: متن finishing در محیط wet و recoating را برای حفظ رنگ و بهبود texture پیشنهاد می کند.',
                    ],
                    [
                        'question' => 'بعد از اعمال و curing کامل sealant، چرا checking و adjustment اکلوزال در صورت نیاز توصیه شده است؟',
                        'options' => [
                            'برای حذف premature occlusal contacts با دندان مقابل',
                            'برای افزایش release فلوراید در ۲۴ ساعت اول',
                            'برای ایجاد salivary contamination کنترل شده',
                            'برای تبدیل light-cured sealant به self-cured material',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن پس از کاربرد و curing کامل، checking occlusion و adjustment در صورت نیاز را برای حذف premature occlusal contacts با opposing tooth توصیه می کند. دلیل رد گزینه های غلط: - گزینه ب: adjustment اکلوزال در متن برای افزایش fluoride release نیست. - گزینه ج: salivary contamination باید جلوگیری یا با rinse/re-etch مدیریت شود. - گزینه د: نوع curing ماده با adjustment اکلوزال تغییر نمی کند.',
                    ],
                    [
                        'question' => 'در کاربرد fluoride varnish برای کودکان high risk، کدام عدد در فصل به عنوان کاهش caries در clinical trials گزارش شده است؟',
                        'options' => [
                            'تا حدود ۷۰٪',
                            '۱۸٪ پس از ۲۴ ماه',
                            '۳۵٪ در ۵ سال',
                            '۹۵٪ retention',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: clinical trials درباره varnish در کودکان at risk کاهش caries را تا ۷۰٪ گزارش کرده اند. دلیل رد گزینه های غلط: - گزینه ب: ۱۸٪ مربوط به کاهش progression caries با sugar-free gum حاوی CPP-ACP است. - گزینه ج: ۳۵٪ اثربخشی کاهش caries در یک مطالعه sealant light-cured پس از ۵ سال است. - گزینه د: ۹۵٪ retention مربوط به sealant بدون فلوراید در مطالعه ۴ ساله است.',
                    ],
                    [
                        'question' => 'glass ionomer sealant با وجود retention ضعیف تر از resin sealant، چه مزیت بالقوه ای پس از loss دارد؟',
                        'options' => [
                            'fluoride deposition بیشتر در enamel و latent caries protection',
                            'wear resistance بالاتر از resin sealant',
                            'نفوذ عمیق تر به کف fissure به دلیل ویسکوزیته کمتر',
                            'عدم brittle بودن در برابر occlusal wear',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: مطالعات GI sealants retention پایین تر از resin sealants ولی fluoride deposition بیشتر در enamel نشان داده اند که باعث potential latent caries protection پس از sealant loss می شود. دلیل رد گزینه های غلط: - گزینه ب: متن GI را more brittle و less resistant to occlusal wear می داند. - گزینه ج: GIها generally viscous هستند و penetration به عمق fissure دشوار است. - گزینه د: بریتلی و wear resistance کمتر از محدودیت های GI به عنوان sealant است.',
                    ],
                    [
                        'question' => 'اندازه گیری fluoride در plaque مجاور ترمیم ها چه تفاوتی بین RMGI و compomer از یک سازنده نشان داد؟',
                        'options' => [
                            'plaque مجاور RMGI در روزهای ۲ و ۲۱ fluoride بالاتری داشت',
                            'plaque مجاور compomer در هر دو زمان fluoride بالاتری داشت',
                            'در هیچ زمانی plaque مجاور RMGI قابل اندازه گیری نبود',
                            'fluoride plaque فقط پس از ۶ ماه ظاهر شد',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در متن، plaque مجاور RMGI نسبت به compomer restorations در ۲ و ۲۱ روز پس از insertion fluoride content بالاتری داشت. دلیل رد گزینه های غلط: - گزینه ب: متن برتری compomer را گزارش نکرده است. - گزینه ج: اندازه گیری plaque انجام شده و مقدار fluoride بالاتر کنار RMGI گزارش شده است. - گزینه د: زمان های ذکرشده ۲ و ۲۱ روز بودند، نه فقط پس از ۶ ماه.',
                    ],
                    [
                        'question' => 'کدام داده به مطالعه طولانی مدت ۱۵ ساله sealant خودسخت شونده unfilled مربوط است؟',
                        'options' => [
                            '۲۷.۶٪ retention کامل و ۳۵.۴٪ retention نسبی',
                            '۹۱٪ retention با ۷۷٪ کامل و ۱۴٪ نسبی',
                            '۸۰٪ retention و ۶۹٪ effectiveness پس از ۳ سال',
                            '۴۲٪ retention و ۳۵٪ effectiveness پس از ۵ سال',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: طولانی ترین مطالعه منتشرشده در متن درباره self-cured unfilled material، ۱۵ ساله بود و ۲۷.۶٪ complete retention و ۳۵.۴٪ partial retention داشت. دلیل رد گزینه های غلط: - گزینه ب: ۹۱٪ با ۷۷٪ complete و ۱۴٪ partial مربوط به fluoride-releasing sealant در مطالعه ۴ ساله است. - گزینه ج: ۸۰٪ و ۶۹٪ مربوط به quicker-setting unfilled resin پس از ۳ سال است. - گزینه د: ۴۲٪ و ۳۵٪ مربوط به light-cured sealant در newly erupting teeth پس از ۵ سال است.',
                    ],
                    [
                        'question' => 'وضعیت کاربرد SDF در متن چگونه توصیف شده است؟',
                        'options' => [
                            'ابتدا به عنوان dentin desensitizer تایید شده بود، اما اکنون استفاده برای arrest caries پذیرفتنی است',
                            'فقط به عنوان pit and fissure sealant بی رنگ تایید شده است',
                            'به عنوان root-end filling material اصلی جایگزین MTA شده است',
                            'برای finishing ترمیم های RMGI در محیط wet استفاده می شود',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید SDF originally approved as a dentin desensitizer بوده، اما اکنون use آن برای arresting caries قابل قبول است. دلیل رد گزینه های غلط: - گزینه ب: SDF در متن sealant pit/fissure نیست. - گزینه ج: root-end filling material در این فصل MTA است. - گزینه د: finishing wet و recoating مربوط به RMGI است.',
                    ],
                    [
                        'question' => 'در حین sealant placement، چرا از تجمع excess material باید اجتناب شود؟',
                        'options' => [
                            'زیرا می تواند با occlusion تداخل کند',
                            'زیرا باعث کاهش کامل retention به صفر می شود',
                            'زیرا fluoride release را از resin sealant حذف می کند',
                            'زیرا enamel etched را به uncut enamel تبدیل می کند',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید buildup اضافی ماده باید اجتناب شود چون می تواند در occlusion اختلال ایجاد کند. دلیل رد گزینه های غلط: - گزینه ب: کاهش retention به صفر در متن به عنوان نتیجه قطعی excess material نیامده است. - گزینه ج: fluoride release به فرمولاسیون ماده مربوط است و excess به طور مستقیم آن را حذف نمی کند. - گزینه د: excess material وضعیت etched بودن enamel را تغییر ماهوی نمی دهد.',
                    ],
                    [
                        'question' => 'کدام جزء در MTA برای radiopacity افزوده می شود؟',
                        'options' => [
                            'bismuth oxide',
                            'zinc stearate',
                            'fumed silica',
                            'casein phosphopeptide',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: ترکیب MTA شامل tricalcium silicate، dicalcium silicate، tricalcium aluminate و bismuth oxide است؛ bismuth oxide برای radiopacity اضافه می شود. دلیل رد گزینه های غلط: - گزینه ب: zinc stearate در catalyst paste برخی calcium hydroxide liners ذکر شده است. - گزینه ج: fumed silica به عنوان filler در sealantهای کم ویسکوز برای stiffness/wear resistance مطرح است. - گزینه د: casein phosphopeptide جزء CPP-ACP remineralization technology است.',
                    ],
                    [
                        'question' => 'در cervical erosion، چرا retention glass ionomer می تواند بهتر از composite گزارش شود با اینکه bond strength آن به dentin کمتر است؟',
                        'options' => [
                            'متن retention بالینی بهتر GI در این نواحی را گزارش می کند و آن را با conditioning و ویژگی های ماده در کاربرد cervical مطرح می سازد',
                            'زیرا GI به dentin هیچ bond ندارد و صرفاً با occlusion نگه داشته می شود',
                            'زیرا composite در فصل برای cervical lesions ممنوع شده است',
                            'زیرا GI پس از ۴ سال کاملاً صاف تر از composite باقی می ماند',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: فصل با وجود bond strength پایین تر GI نسبت به resin composites، retention آن را در cervical erosion بهتر گزارش می کند و کاربرد بدون cavity preparation پس از conditioning با polyacrylic acid را مطرح می کند. دلیل رد گزینه های غلط: - گزینه ب: GI adhesion به tooth surface از طریق chelation دارد و بدون bond توصیف نشده است. - گزینه ج: composite برای cervical lesions ممنوع معرفی نشده است؛ اما GI/RMGI برای high caries risk و شرایط خاص توصیه شده اند. - گزینه د: در مطالعات ۴ ساله GI rough surface و برخی shade mismatch دیده شد.',
                    ],
                    [
                        'question' => 'در root caries cavitated، مقایسه toothpasteهای فلورایدی در فصل چه نتیجه ای نشان داد؟',
                        'options' => [
                            '۵۰۰۰ ppm-F، ۷۶٪ lesions را rehardening کرد؛ ۱۱۰۰ ppm-F، ۳۵٪ را',
                            '۱۱۰۰ ppm-F، ۷۶٪ lesions را rehardening کرد؛ ۵۰۰۰ ppm-F، ۳۵٪ را',
                            'هر دو غلظت rehardening صفر داشتند',
                            '۵۰۰۰ ppm-F فقط staining ایجاد کرد و اثری بر rehardening نداشت',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن گزارش می کند toothpaste با ۵۰۰۰ ppm-F باعث rehardening ۷۶٪ ضایعات و گروه ۱۱۰۰ ppm-F باعث rehardening ۳۵٪ شد. دلیل رد گزینه های غلط: - گزینه ب: اعداد در متن برعکس نیستند. - گزینه ج: اثر rehardening برای هر دو گزارش شده است، نه صفر. - گزینه د: staining مشکل SDF است، نه toothpaste ۵۰۰۰ ppm-F در این بخش.',
                    ],
                    [
                        'question' => 'کدام ویژگی نگهداری و shelf life برای light-cured sealants در فصل آمده است؟',
                        'options' => [
                            'عرضه در light-proof containers با shelf life بیش از ۱۲ ماه',
                            'نگهداری در آب برای فعال سازی اولیه و shelf life کمتر از یک هفته',
                            'نگهداری بدون محافظ نوری چون قبل از تماس با بزاق cure نمی شوند',
                            'عرضه فقط به شکل powder-liquid capsule',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: light-cured sealants در light-proof containers عرضه می شوند و باید shelf life بیش از ۱۲ ماه داشته باشند. دلیل رد گزینه های غلط: - گزینه ب: نگهداری در آب و shelf life کمتر از یک هفته در متن نیست. - گزینه ج: نیاز به ظرف light-proof نشان می دهد محافظت نوری مهم است. - گزینه د: powder-liquid capsule توصیف GI/RMGI است، نه شکل اختصاصی light-cured sealant.',
                    ],
                    [
                        'question' => 'چرا glass ionomer materials در فصل برای operative dentistry جمعیت سالمند و xerostomia اهمیت بیشتری پیدا می کنند؟',
                        'options' => [
                            'به دلیل شیوع root caries، کاهش salivary flow و caries risk بالا در این گروه ها',
                            'به دلیل نیاز اصلی این گروه به opaque sealant در مولرهای بدون fissure',
                            'به دلیل ممنوعیت استفاده از composite در تمام کودکان',
                            'به دلیل hardening چندروزه که زمان کار را طولانی می کند',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: فصل اهمیت فزاینده glass ionomer materials را برای aging population با root caries، بیماران xerostomia/reduced salivary flow و کودکان با caries risk بالا ذکر می کند. دلیل رد گزینه های غلط: - گزینه ب: opaque sealant موضوع recall در pit/fissure sealants است و دلیل اصلی این بخش نیست. - گزینه ج: متن ممنوعیت کلی composite در کودکان را بیان نمی کند. - گزینه د: hardening چندروزه مربوط به MTA است و مزیت GI/RMGI برای این جمعیت ها نیست.',
                    ],
                    [
                        'question' => 'کدام fillerها در متن برای افزایش stiffness و wear resistance sealantهای رزینی ذکر شده اند؟',
                        'options' => [
                            'fumed silica یا silanated inorganic glasses',
                            'bismuth oxide و tricalcium aluminate',
                            'calcium hydroxide و zinc stearate',
                            'CPP و amorphous calcium phosphate',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: برای افزایش stiffness و wear resistance، filler particles از fumed silica یا silanated inorganic glasses به resin sealant افزوده می شوند. دلیل رد گزینه های غلط: - گزینه ب: bismuth oxide و tricalcium aluminate مربوط به MTA هستند. - گزینه ج: calcium hydroxide و zinc stearate در calcium hydroxide liner مطرح اند. - گزینه د: CPP-ACP در remineralization technology مطرح است، نه filler sealant رزینی.',
                    ],
                    [
                        'question' => 'در linerهای glass ionomer زیر composite، تفاوت adhesion به composite بین GI معمولی و RMGI چگونه آمده است؟',
                        'options' => [
                            'GI معمولی عمدتاً با mechanical retention و RMGI با mechanical و chemical bonding',
                            'GI معمولی با chemical bonding و RMGI بدون هیچ adhesion',
                            'هر دو فقط با fluoride recharge به composite می چسبند',
                            'هر دو فقط با bismuth oxide به composite متصل می شوند',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید glass ionomer lining materials به composite restoratives می چسبند؛ برای conventional GI mechanical retention و برای RMGI mechanical and chemical bonding مطرح است. دلیل رد گزینه های غلط: - گزینه ب: توصیف متن برعکس نیست و RMGI بدون adhesion معرفی نشده است. - گزینه ج: fluoride recharge به release/rerelease مربوط است، نه مکانیسم اتصال به composite. - گزینه د: bismuth oxide جزء MTA برای radiopacity است.',
                    ],
                    [
                        'question' => 'یک مطالعه ۲۴ ماهه flowable composite را با fluoride-containing fissure sealant مقایسه کرده است. نتیجه گزارش شده کدام است؟',
                        'options' => [
                            'retention و caries incidence معادل بودند',
                            'flowable composite retention صفر و caries بیشتر داشت',
                            'fluoride sealant در همه سطوح enamel loss ایجاد کرد',
                            'flowable composite فقط در denture base کاربرد داشت',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در متن آمده که در مطالعه ۲۴ ماهه، retention و caries incidence flowable composite معادل fluoride-containing fissure sealant بود. دلیل رد گزینه های غلط: - گزینه ب: متن retention صفر یا caries بیشتر برای flowable composite گزارش نکرده است. - گزینه ج: enamel loss برای fluoride sealant در این مطالعه ذکر نشده است. - گزینه د: flowable composite برای preventive resin restorations، liners، repairs و cervical restorations ذکر شده، نه denture base.',
                    ],
                    [
                        'question' => 'درباره solubility calcium hydroxide liner، کدام تفسیر مطابق فصل است؟',
                        'options' => [
                            'solubility قابل توجه است و مقداری solubility برای اثر درمانی لازم است، هرچند optimum معلوم نیست',
                            'solubility باید کاملاً صفر باشد تا secondary dentin تشکیل شود',
                            'solubility فقط در presence fluoride dentifrice دیده می شود',
                            'solubility باعث می شود etching و varnish همیشه بی خطر باشند',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن solubility calcium hydroxide bases را significant می داند و می گوید مقداری solubility برای therapeutic properties لازم است، اما مقدار optimum مشخص نیست. دلیل رد گزینه های غلط: - گزینه ب: متن solubility صفر را شرط درمانی نمی داند. - گزینه ج: وابستگی solubility به fluoride dentifrice در متن نیامده است. - گزینه د: به دلیل solubility، acid-etching procedures و varnish در حضور CaOH liners باید با احتیاط انجام شوند.',
                    ],
                    [
                        'question' => 'پلیمریزاسیون light-cured sealant در فصل با کدام سیستم آغازگر توضیح داده شده است؟',
                        'options' => [
                            'photosensitive diketone همراه با tertiary amine',
                            'polyacrylic acid همراه با aluminosilicate glass',
                            'calcium hydroxide همراه با glycol salicylate',
                            'silver ions همراه با alkaline water',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: آغاز polymerization در light-cured sealants با photosensitive diketone در conjunction با tertiary amine توضیح داده شده است. دلیل رد گزینه های غلط: - گزینه ب: polyacrylic acid و aluminosilicate glass به glass ionomer مربوط است. - گزینه ج: calcium hydroxide و salicylate به calcium hydroxide liner مربوط است. - گزینه د: silver ions و alkaline water به SDF نزدیک است، نه initiation sealant.',
                    ],
                    [
                        'question' => 'محصول اصلی واکنش MTA با آب در فصل چیست؟',
                        'options' => [
                            'calcium hydroxide',
                            'amorphous calcium phosphate',
                            'polyacrylic acid gel',
                            'silver nitrate',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: فصل تصریح می کند main reaction product از مخلوط MTA و water، calcium hydroxide است. دلیل رد گزینه های غلط: - گزینه ب: amorphous calcium phosphate در CPP-ACP و remineralization مطرح است. - گزینه ج: polyacrylic acid gel واکنش اصلی MTA نیست. - گزینه د: silver nitrate پیش درآمد کاربرد مشابه SDF برای caries بود، نه محصول MTA.',
                    ],
                    [
                        'question' => 'برای بهبود کاربرد glass ionomer روی dentin در cervical restoration بدون cavity preparation، کدام conditioning در متن آمده است؟',
                        'options' => [
                            'dilute polyacrylic acid با غلظت ۱۵٪ تا ۲۵٪',
                            'phosphoric acid ۳۵٪ تا ۴۰٪ برای ۱۵ تا ۳۰ ثانیه',
                            '۱٪ difluorosilane با ۱۰۰۰ ppm F−',
                            '۳۰٪ SDF با pH حدود ۱۰',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید وقتی dentin با dilute polyacrylic acid ۱۵٪ تا ۲۵٪ conditioned شود، glass ionomer می تواند بدون cavity preparation اعمال شود. دلیل رد گزینه های غلط: - گزینه ب: phosphoric acid ۳۵٪ تا ۴۰٪ مربوط به enamel etching برای sealant است. - گزینه ج: difluorosilane مربوط به fluoride varnish است. - گزینه د: SDF برای arrest caries/desensitizer مطرح است، نه conditioning GI.',
                    ],
                    [
                        'question' => 'کدام ترکیب setting برای RMGI در فصل درست است؟',
                        'options' => [
                            'acid-base ionomer reaction همراه با light-cured resin polymerization مونومرها مانند HEMA',
                            'فقط self-cure از طریق salicylate و calcium hydroxide',
                            'فقط hardening چندروزه از طریق Portland cement بدون light-curing',
                            'فقط evaporation solvent و تشکیل thin film روی دندان',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: RMGI با ترکیب acid-base ionomer reaction و light-cured resin polymerization مونومرها، معمولاً 2-hydroxyethyl methacrylate، set می شود. دلیل رد گزینه های غلط: - گزینه ب: salicylate و calcium hydroxide به CaOH liner مربوط اند. - گزینه ج: hardening چندروزه و Portland cement به MTA مربوط است. - گزینه د: evaporation solvent و thin film مربوط به fluoride varnish است.',
                    ],
                    [
                        'question' => 'کدام مجموعه ویژگی ها بهترین adhesion sealant به enamel را در فصل توضیح می دهد؟',
                        'options' => [
                            'low surface tension، wetting خوب و low viscosity',
                            'high surface tension، contact angle بالا و low flow',
                            'opacity بالا، setting چندروزه و solubility زیاد',
                            'pH قلیایی، silver بالا و staining زیاد',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: optimal adhesion sealant به enamel زمانی رخ می دهد که sealant low surface tension، good wetting و low viscosity داشته باشد تا روی enamel flow و spread شود. دلیل رد گزینه های غلط: - گزینه ب: high surface tension و contact angle بالا نشان دهنده wetting ضعیف است. - گزینه ج: setting چندروزه و solubility زیاد به sealant adhesion مربوط نیست و به مواد دیگر نزدیک است. - گزینه د: pH قلیایی و silver/staining ویژگی SDF است.',
                    ],
                    [
                        'question' => 'کدام کاربرد بالقوه fluoride varnish علاوه بر کودکان high caries risk در متن مطرح شده است؟',
                        'options' => [
                            'پیشگیری از root caries در جمعیت مسن با exposed root surfaces',
                            'جایگزینی کامل MTA در root-end filling',
                            'افزایش opacity sealant برای recall',
                            'افزایش air-inhibited layer برای resin curing',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن potential use دیگر varnish را prevention of root caries در older population با exposed root surfaces بیان می کند. دلیل رد گزینه های غلط: - گزینه ب: root-end filling مربوط به MTA است. - گزینه ج: opacity sealant برای recall مربوط به sealantهای opaque/tinted است. - گزینه د: air-inhibited layer یک مشکل resin curing است و هدف varnish نیست.',
                    ],
                    [
                        'question' => 'در کار با preencapsulated glass ionomer، چرا minimum manipulation پس از placement مهم است؟',
                        'options' => [
                            'اختلال gel stage در early reaction خواص فیزیکی را بسیار کم و adhesion را از بین می برد',
                            'باعث تبدیل ماده به RMGI و افزایش HEMA می شود',
                            'باعث آزادسازی بیش از حد فلوراید در varnish می شود',
                            'مانع ایجاد staining سیاه توسط silver می شود',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید working time کوتاه و critical است؛ اگر gel stage واکنش در early phase مختل شود، physical properties بسیار پایین می آید و adhesion از دست می رود. دلیل رد گزینه های غلط: - گزینه ب: manipulation ماده را به RMGI تبدیل نمی کند. - گزینه ج: فلوراید varnish موضوع جداگانه ای است. - گزینه د: staining سیاه مربوط به SDF و نقره است.',
                    ],
                    [
                        'question' => 'افزودن CPP-ACP به sugar-free gum در trial بالینی چه اثری نشان داد؟',
                        'options' => [
                            '۱۸٪ کاهش caries progression پس از ۲۴ ماه',
                            '۷۰٪ کاهش caries در کودکان با varnish',
                            '۲۷.۶٪ retention کامل پس از ۱۵ سال',
                            '۷۶٪ rehardening با ۵۰۰۰ ppm-F toothpaste',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در randomized controlled trial، افزودن CPP-ACP به sugar-free gum باعث ۱۸٪ کاهش caries progression پس از ۲۴ ماه شد. دلیل رد گزینه های غلط: - گزینه ب: ۷۰٪ مربوط به اثربخشی fluoride varnish در کودکان at risk است. - گزینه ج: ۲۷.۶٪ مربوط به retention کامل self-cured unfilled sealant در مطالعه ۱۵ ساله است. - گزینه د: ۷۶٪ مربوط به rehardening با toothpaste ۵۰۰۰ ppm-F است.',
                    ],
                    [
                        'question' => 'عمق نفوذ sealant در etched enamel که tags مسیول bonding را ایجاد می کند، در متن چه محدوده ای دارد؟',
                        'options' => [
                            '۲۵ تا ۵۰ μm',
                            '۲ تا ۳ MPa',
                            '۱۰ تا ۲۰ ثانیه',
                            '۹.۲ تا ۱۱.۷',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در فصل، penetration sealant into etched enamel و تشکیل tags به عمق ۲۵ تا ۵۰ μm نشان داده شده است. دلیل رد گزینه های غلط: - گزینه ب: ۲ تا ۳ MPa bond strength glass ionomer به dentin است. - گزینه ج: ۱۰ تا ۲۰ ثانیه زمان cure light-cured sealant/RMGI است. - گزینه د: ۹.۲ تا ۱۱.۷ pH محصولات calcium hydroxide است.',
                    ],
                    [
                        'question' => 'طبق متن، کدام دوره کاربرد برای fluoride varnish بیشترین کارایی را نشان می دهد؟',
                        'options' => [
                            'semiannual application',
                            'روزانه پس از هر وعده غذایی',
                            'هر ۱۰ تا ۲۰ ثانیه با curing light',
                            'فقط یک بار در کودکی',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید semiannual application of fluoride varnishes seems to provide optimum efficacy. دلیل رد گزینه های غلط: - گزینه ب: کاربرد روزانه پس از هر وعده در متن برای varnish ذکر نشده است. - گزینه ج: ۱۰ تا ۲۰ ثانیه مربوط به light curing sealants/RMGIs است. - گزینه د: یک بار در کودکی با توصیه semiannual سازگار نیست.',
                    ],
                    [
                        'question' => 'در glass ionomer cervical restoration، اگر ضخامت dentin باقی مانده کمتر از ۱ mm باشد، متن چه توصیه ای می کند؟',
                        'options' => [
                            'استفاده از calcium hydroxide liner',
                            'حذف کامل fluoride release با varnish',
                            'کاربرد SDF به جای هر ترمیم',
                            'عدم conditioning با polyacrylic acid در همه موارد',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: اگر ضخامت dentin کمتر از ۱ mm باشد، منبع calcium hydroxide liner را توصیه می کند؛ pulp reaction به GI معمولاً mild است. دلیل رد گزینه های غلط: - گزینه ب: حذف fluoride release هدف نیست. - گزینه ج: SDF برای arrest caries مطرح است و جایگزین همه ترمیم ها در این توصیه نیست. - گزینه د: polyacrylic acid conditioning برای GI در cervical lesions ذکر شده است.',
                    ],
                    [
                        'question' => 'RMGI restoration در فصل بیشتر برای کدام وضعیت توصیه شده است؟',
                        'options' => [
                            'low stress–bearing areas و بیماران high caries risk',
                            'heavy occlusal load without enamel support',
                            'root-end filling endodontic با setting چندروزه',
                            'تبدیل carious dentin به black-stained arrested lesion',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: RMGIs برای restorations در low stress–bearing areas و بیماران high caries risk توصیه شده اند. دلیل رد گزینه های غلط: - گزینه ب: heavy occlusal load بدون support با نقش RMGI در متن سازگار نیست. - گزینه ج: root-end filling با setting چندروزه مربوط به MTA است. - گزینه د: black-stained arrested lesion به SDF مربوط است.',
                    ],
                    [
                        'question' => 'flowable composites در فصل معمولاً با کدام delivery systems معرفی شده اند؟',
                        'options' => [
                            'syringes یا capsules برای direct application',
                            'light-proof containers فقط برای shelf life sealant',
                            'powder-liquid bulk فقط برای hand spatulation',
                            'organic solvent system که با رطوبت thin film بسازد',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: flowable composites معمولاً در syringes یا capsules برای direct application به pit یا fissure بسته بندی می شوند. دلیل رد گزینه های غلط: - گزینه ب: light-proof containers مربوط به light-cured sealants است. - گزینه ج: powder-liquid bulk بیشتر برای glass ionomers/RMGIs مطرح است. - گزینه د: organic solvent و thin film مربوط به fluoride varnish است.',
                    ],
                    [
                        'question' => 'در calcium hydroxide liners، کدام fillerها برای radiopacity ذکر شده اند؟',
                        'options' => [
                            'calcium tungstate یا barium sulfate',
                            'fumed silica یا silanated glass',
                            'bismuth oxide یا silver nitrate',
                            'casein phosphopeptide یا amorphous calcium phosphate',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: فصل بیان می کند fillers مانند calcium tungstate یا barium sulfate radiopacity فراهم می کنند. دلیل رد گزینه های غلط: - گزینه ب: fumed silica/silanated glass fillers sealant برای stiffness/wear resistance هستند. - گزینه ج: bismuth oxide در MTA برای radiopacity است و silver nitrate پیشینه SDF است. - گزینه د: CPP-ACP برای remineralization است، نه radiopacity liner.',
                    ],
                    [
                        'question' => 'کدام oligomer base به عنوان alternative اما مشابه Bis-GMA برای sealants در فصل آمده است؟',
                        'options' => [
                            'urethane dimethacrylate',
                            '2-hydroxyethyl methacrylate',
                            'glycol salicylate',
                            'calcium sodium phosphosilicate',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن urethane dimethacrylate را به عنوان alternative but similar oligomer base معرفی می کند و برخی مواد از ترکیب دو base resin ساخته می شوند. دلیل رد گزینه های غلط: - گزینه ب: HEMA مونومر رایج در RMGI است. - گزینه ج: glycol salicylate در base paste calcium hydroxide liner ذکر شده است. - گزینه د: calcium sodium phosphosilicate همان bioactive glass است.',
                    ],
                    [
                        'question' => 'اثر antibacterial قوی SDF در متن عمدتاً به کدام جزء نسبت داده شده است؟',
                        'options' => [
                            'silver',
                            'fluoride',
                            'bismuth oxide',
                            'polyacrylic acid',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: هرچند pH بالا acid neutralization ایجاد می کند، متن اثر antibacterial قوی SDF را عمدتاً به حضور silver، یک antimicrobial شناخته شده، نسبت می دهد. دلیل رد گزینه های غلط: - گزینه ب: fluoride در SDF وجود دارد، اما عامل اصلی antibacterial در متن silver معرفی شده است. - گزینه ج: bismuth oxide جزء radiopaque MTA است. - گزینه د: polyacrylic acid مربوط به GI conditioning/liquid است.',
                    ],
                    [
                        'question' => 'درباره resin sealants با ادعای fluoride release، کدام گزاره دقیق تر است؟',
                        'options' => [
                            'release در ۲۴ ساعت اول بیشترین است و سپس به سطح نگهدارنده پایین taper می کند، اما بهبود معنی دار clinical protection ثابت نشده است',
                            'release پس از ۳۰ روز شروع می شود و caries را در همه مطالعات صفر می کند',
                            'release فقط زمانی رخ می دهد که sealant کاملاً از دست برود',
                            'release با opaque بودن ماده کاملاً حذف می شود',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید release فلوراید در resin sealants در ۲۴ ساعت اول highest است، سپس به low maintenance level کاهش می یابد و تاکنون improvement معنی دار در clinical protection against caries ثابت نشده است. دلیل رد گزینه های غلط: - گزینه ب: شروع release پس از ۳۰ روز و caries صفر برای همه مطالعات ذکر نشده است. - گزینه ج: release به loss کامل sealant وابسته معرفی نشده است. - گزینه د: opaque بودن برای recall/visibility است و حذف fluoride release ذکر نشده است.',
                    ],
                    [
                        'question' => 'چرا RMGIها نسبت به conventional glass ionomers estheticتر توصیف شده اند؟',
                        'options' => [
                            'به دلیل resin content',
                            'به دلیل وجود bismuth oxide',
                            'به دلیل pH حدود ۱۰ و silver content',
                            'به دلیل absence کامل fluoride release',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید RMGI restorations به دلیل resin content از glass ionomers estheticترند. دلیل رد گزینه های غلط: - گزینه ب: bismuth oxide جزء MTA است. - گزینه ج: pH حدود ۱۰ و silver content مربوط به SDF است و staining ایجاد می کند. - گزینه د: RMGIها fluoride release دارند؛ absence کامل release در متن نیست.',
                    ],
                    [
                        'question' => 'در مطالعه ای با quicker-setting unfilled resin sealant و penetration خوب، کدام نتیجه گزارش شد؟',
                        'options' => [
                            '۸۰٪ retention و ۶۹٪ effectiveness پس از ۳ سال',
                            '۵۳٪ retention و ۵۴٪ effectiveness پس از ۴ سال',
                            '۴۲٪ retention و ۳۵٪ effectiveness پس از ۵ سال',
                            '۷۷٪ complete و ۱۴٪ partial retention پس از ۴ سال',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در متن، quicker-setting unfilled resin sealant با penetration خوب، ۸۰٪ retention و ۶۹٪ effectiveness پس از ۳ سال داشت. دلیل رد گزینه های غلط: - گزینه ب: ۵۳٪ و ۵۴٪ مربوط به filled resin sealant پس از ۴ سال است. - گزینه ج: ۴۲٪ و ۳۵٪ مربوط به light-cured sealant در newly erupting teeth پس از ۵ سال است. - گزینه د: ۷۷٪ complete و ۱۴٪ partial بخشی از داده fluoride-releasing sealant در مطالعه ۴ ساله است.',
                    ],
                    [
                        'question' => 'کدام گزینه، نوع دوم fluoride varnish ذکرشده در فصل را نشان می دهد؟',
                        'options' => [
                            '۱٪ difluorosilane با ۰.۱٪ F− یا ۱۰۰۰ ppm',
                            '۵٪ sodium fluoride با ۵٪ silver',
                            '۳۰٪ SDF با ۲.۲۶٪ F−',
                            '۱۵٪ polyacrylic acid با ۲۲۶۰۰ ppm',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: محصولات varnish طبق متن یا ۵٪ sodium fluoride دارند یا ۱٪ difluorosilane با ۰.۱٪ F− یا ۱۰۰۰ ppm. دلیل رد گزینه های غلط: - گزینه ب: ۵٪ sodium fluoride شامل silver نیست. - گزینه ج: ۳۰٪ SDF همان varnish فلورایدی این بخش نیست و ترکیب متفاوت دارد. - گزینه د: ۱۵٪ polyacrylic acid conditioning GI است، نه varnish.',
                    ],
                    [
                        'question' => 'مزیت اصلی light-cured sealant نسبت به self-cured از نظر handling در فصل چیست؟',
                        'options' => [
                            'operator می تواند working time را کاملاً کنترل کند',
                            'نیازی به light-proof container ندارد',
                            'لایه air-inhibited ایجاد نمی کند',
                            'بدون etching به enamel bond قوی تری دارد',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در متن advantage استفاده از light-cured sealant این است که working time توسط operator کاملاً کنترل می شود. دلیل رد گزینه های غلط: - گزینه ب: light-cured sealants در light-proof containers عرضه می شوند. - گزینه ج: air-inhibited surface layer پس از light curing در resin curing باقی می ماند. - گزینه د: bonding به etched enamel متکی است و etching حذف نمی شود.',
                    ],
                    [
                        'question' => 'RMGI و glass ionomer پس از مواجهه با fluoride treatments یا fluoride dentifrices چه رفتاری نشان می دهند؟',
                        'options' => [
                            'recharge و rerelease فلوراید',
                            'تبدیل کامل به calcium disalicylate',
                            'از دست دادن فوری همه bond به dentin',
                            'تبدیل به SDF با staining سیاه',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: هر دو ionomer materials در متن پس از exposure به fluoride treatments یا fluoride dentifrices قابلیت recharge و سپس rerelease دارند. دلیل رد گزینه های غلط: - گزینه ب: calcium disalicylate مربوط به CaOH liner است. - گزینه ج: متن از دست رفتن فوری bond به dentin را با fluoride exposure بیان نکرده است. - گزینه د: SDF ماده ای جداگانه با silver است.',
                    ],
                ],
            ],
            [
                'slug' => '9-1',
                'path' => '/exams/craig-dental-materials/6-10/9-1/',
                'questionCount' => 40,
                'label' => 'فصل ۹ - قسمت اول',
                'title' => 'آزمون فصل ۹ - قسمت اول',
                'subtitle' => '۴۰ سؤال چهارگزینه‌ای از مجموعه کریگ.',
                'description' => 'مرور ۴۰ سؤال از مجموعه کریگ.',
                'eyebrow' => 'کریگ | فصول ۶ تا ۱۰ | فصل ۹ - قسمت اول',
                'backHref' => '/exams/craig-dental-materials/6-10/',
                'backLabel' => 'بازگشت به فصول ۶ تا ۱۰ کریگ',
                'autoAdvance' => true,
                'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'siteSubtitle' => 'آزمون‌ها',
                'siteBadge' => 'آزمون رفرنسی',
                'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'questions' => [
                    [
                        'question' => 'در یک ترمیم MOD که هدف اصلی کاهش مراحل لایه گذاری و رسیدن به عمق کیور بیشتر است، مطابق توصیه های فصل، مناسب ترین خانواده مواد کدام است؟',
                        'options' => [
                            'Microfilled composite و compomer',
                            'Bulk filled composite و nanocomposite',
                            'Flowable composite و RMGI',
                            'Laboratory composite و provisional composite',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: برای Class 6 (MOD) در جدول فصل، Bulk filled و nanocomposite توصیه شده اند و bulk fillها برای depth of cure بیشتر و کاهش مراحل طراحی شده اند. رد الف: microfilled/compomer به طور اختصاصی پاسخ کلاس 6 نیستند. رد ج: flowable/RMGI برای cervical/pediatric و موارد کم استرس مناسب ترند. رد د: laboratory/provisional برای bridge، substructure یا temporary restoration مطرح اند، نه MOD کلاس 6.',
                    ],
                    [
                        'question' => 'در یک کامپوزیت، اگر پس از مدتی کارکرد، ذرات فیلر از ماتریکس جدا شوند و سطح زبر شود، کدام جزء بیشترین ارتباط مستقیم با پیشگیری از این پدیده دارد؟',
                        'options' => [
                            'Pigmentهای اکسیدی برای Shade',
                            'Coupling agent از نوع silane',
                            'UV absorberهای افزوده شده به خمیر',
                            'Diluent monomerهای کم وزن مولکولی',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: silane coupling agent پل بین filler inorganic و resin matrix ایجاد می کند و plucking فیلر را در wear کم می کند. رد الف: pigment Shade می دهد و عامل باند فیلر-ماتریکس نیست. رد ج: UV absorber برای کاهش تغییر رنگ اکسیداتیو است. رد د: diluent monomer ویسکوزیته را کنترل می کند، نه اتصال مستقیم filler به matrix را.',
                    ],
                    [
                        'question' => 'کدام توصیف درباره جایگاه GIs و RMGIs در فصل درست تر است؟',
                        'options' => [
                            'به دلیل ماتریکس بدون آب، در گروه کامپوزیت های صرفاً رزینی قرار می گیرند.',
                            'از نظر علمی در گستره کامپوزیت ها هستند، اما به علت آب پایه بودن و واکنش acid-base جداگانه طبقه بندی می شوند.',
                            'به علت نبود فیلر معدنی، از دسته مواد کامپوزیتی خارج اند.',
                            'تنها به دلیل داشتن initiator-accelerator system در گروه resin composites قرار می گیرند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: متن می گوید GIs و RMGIs از نظر علمی composite هستند، اما به علت water-based بودن و acid-base setting reaction جداگانه طبقه بندی می شوند. رد الف: ماتریکس آن ها بی آب نیست. رد ج: فیلر/reactive glass دارند. رد د: طبقه بندی آن ها صرفاً بر اساس initiator-accelerator resin composite نیست.',
                    ],
                    [
                        'question' => 'یک ترمیم کامپوزیتی در حفره ای با چسبندگی محیطی به enamel و dentin پلیمریزه می شود. کدام پیامد مستقیماً از ترکیب shrinkage و افزایش modulus هنگام کیور ناشی می شود؟',
                        'options' => [
                            'افزایش plasticity در مراحل دستکاری و کاهش نیاز به باندینگ',
                            'ایجاد contraction stress در interface و احتمال marginal leakage',
                            'افزایش فوری water sorption و کاهش کامل stress در چند دقیقه اول',
                            'حذف air-inhibited layer و کاهش امکان افزودن increment بعدی',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: volumetric shrinkage همراه با افزایش elastic modulus هنگام cure، contraction stress در interface ایجاد می کند و می تواند marginal leakage، stain یا recurrent caries بدهد. رد الف: shrinkage باعث plasticity سودمند یا حذف باندینگ نمی شود. رد ج: water sorption آهسته است و در چند دقیقه stress را کامل حذف نمی کند. رد د: air-inhibited layer حذف نمی شود و برای layering سودمند است.',
                    ],
                    [
                        'question' => 'علت اصلی حفظ Polish در یک nanofilled composite واقعی، در مقایسه با microhybrid یا nanohybrid، کدام است؟',
                        'options' => [
                            'بزرگ تر بودن ذرات اصلی و افزایش مقاومت آن ها به سایش',
                            'shear شدن nanoclusterها با نرخی مشابه ماتریکس اطراف هنگام abrasion',
                            'نبود کامل coupling agent و کاهش جداشدن ذرات از ماتریکس',
                            'افزایش نسبت ذرات opaque و کاهش scattering نور مریی',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: در nanofilled composite، nanoclusterها هنگام abrasion با نرخی شبیه matrix shear می شوند و polish retention حفظ می شود. رد الف: افزایش ذره بزرگ تر علت dull شدن در hybrid/nanohybrid است. رد ج: coupling agent حذف نمی شود. رد د: opaque particle و کاهش translucency توضیح polish retention نیست.',
                    ],
                    [
                        'question' => 'در فرمولاسیون ماتریکس رزینی، چرا به Bis-GMA به ویژه diluent monomer افزوده می شود؟',
                        'options' => [
                            'برای تبدیل cationic polymerization به acid-base reaction',
                            'برای کاهش و کنترل ویسکوزیته هنگام اختلاط با فیلر',
                            'برای حذف نیاز به فیلرهای radiopaque در کامپوزیت',
                            'برای افزایش درصد آب و فعال سازی fluoride release',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: Bis-GMA بسیار viscous است و diluent monomers مثل TEGDMA یا Bis-EMA6 برای کاهش/کنترل viscosity و ایجاد consistency قابل کار افزوده می شوند. رد الف: acid-base reaction مربوط به GIs است. رد ج: radiopacity به فیلرهای حاوی heavy-metal oxide مربوط است. رد د: آب و fluoride release مربوط به GI/RMGI است، نه این diluentها.',
                    ],
                    [
                        'question' => 'کدام بسته بندی برای light-cured composites با منطق فصل سازگارتر است؟',
                        'options' => [
                            'دو خمیر شفاف در سرنگ های جداگانه برای اختلاط شیمیایی',
                            'ظرف پودر-مایع برای فعال سازی acid-base reaction',
                            'سرنگ یا compule پلاستیکی مات، غالباً سیاه، برای پیشگیری از کیور زودرس',
                            'کپسول تک واحدی حاوی آب و FAS glass برای trituration',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
. دلیل: light-cured composites در سرنگ ها یا compuleهای opaque، غالباً سیاه، بسته بندی می شوند تا از premature curing جلوگیری شود. رد الف: دو خمیر شفاف ویژگی self/dual-cured chemical systems است. رد ب: powder-liquid acid-base مربوط به GI است. رد د: کپسول FAS glass/water برای ionomerها است، نه light-cured composite resin.',
                    ],
                    [
                        'question' => 'کامپوزیت های macrofill اولیه با کدام الگوی فیلری و پیامد بالینی شناخته می شوند؟',
                        'options' => [
                            'ذرات 20 تا 30 میکرومتر، ظاهر نسبتاً opaque و مقاومت کم به wear',
                            'ذرات 0.04 میکرومتر، بهترین polish و کمترین shrinkage',
                            'نانوذرات 1 تا 100 نانومتر، translucency بالا و polish retention طولانی',
                            'مخلوط 0.4 تا 5 میکرومتر با نانوذرات افزوده، bulk cure تا 4 میلی متر',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: macrofillهای اولیه ذرات متوسط 20 تا 30 μm داشتند، opaque بودند و wear resistance پایین داشتند. رد ب: 0.04 μm و best polish مربوط به microfilled است. رد ج: 1 تا 100 nm و translucency/polish retention مربوط به nanofill است. رد د: توصیف nanohybrid/bulk fill را مخلوط کرده و macrofill نیست.',
                    ],
                    [
                        'question' => 'در Shadeهای darker و opaque یک کامپوزیت، کدام تغییر در پروتکل کیور از محتوای فصل قابل انتظارتر است؟',
                        'options' => [
                            'کاهش زمان exposure، چون opacifierها گرما تولید می کنند.',
                            'استفاده از incrementهای کوچک تر و زمان exposure طولانی تر',
                            'حذف light curing و اتکا به self-cure در تمام موارد',
                            'افزایش فاصله tip از سطح برای توزیع یکنواخت تر نور',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: Shadeهای dark/opaque نور را بیشتر scatter می کنند، depth of cure کمتر دارند و به exposure longer و increment smaller نیاز دارند. رد الف: کاهش exposure خلاف متن است. رد ج: همه موارد نیاز به self-cure ندارند. رد د: افزایش فاصله tip شدت را کاهش می دهد.',
                    ],
                    [
                        'question' => 'در سیستم silorane، کدام جفت ویژگی به درستی با monomer و polymerization آن مرتبط است؟',
                        'options' => [
                            'siloxane: fluoride release؛ oxirane: free-radical addition',
                            'siloxane: hydrophobicity؛ oxirane: ring-opening cationic polymerization',
                            'siloxane: radiopacity؛ oxirane: acid-base setting',
                            'siloxane: water sorption؛ oxirane: amine-peroxide activation',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: silorane از siloxane و oxirane ساخته شده؛ siloxane hydrophobicity می دهد و oxirane با ring-opening cationic polymerization واکنش می دهد. رد الف: fluoride release و free-radical addition به این اجزا نسبت داده نمی شود. رد ج: radiopacity و acid-base setting مربوط به فیلر/GI است. رد د: amine-peroxide activation روش methacrylate self-cure است، نه oxirane silorane.',
                    ],
                    [
                        'question' => 'اگر هدف، قابل مشاهده تر شدن ترمیم کامپوزیتی در radiograph باشد، کدام فیلر یا افزودنی به طور مستقیم با این هدف مرتبط است؟',
                        'options' => [
                            'microfine silica خالص',
                            'quartz بدون ترکیب با فیلر دیگر',
                            'glassهای دارای barium یا zinc oxide',
                            'prepolymerized resin بدون فاز معدنی',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
. دلیل: glassهای حاوی heavy-metal oxides مثل barium یا zinc radiopacity ایجاد می کنند. رد الف و ب: silica، quartz و lithium-aluminum glass به خودی خود radiopaque نیستند و باید با فیلر دیگر blend شوند. رد د: prepolymerized resin فاز radiopaque معدنی فراهم نمی کند.',
                    ],
                    [
                        'question' => 'در methacrylate composites، توالی اصلی واکنش پلیمریزاسیون کدام است؟',
                        'options' => [
                            'hydrolysis، ionization، salt bridge، maturation',
                            'initiation، propagation، termination',
                            'sintering، silanization، opacification، polishing',
                            'diffusion، sorption، leaching، staining',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: free-radical addition polymerization در methacrylate composites شامل initiation، propagation و termination است. رد الف: مراحل acid-base setting در GI است. رد ج: مربوط به آماده سازی/طبقه بندی فیلر و سطح است. رد د: sorption/leaching/staining واکنش پلیمریزاسیون نیستند.',
                    ],
                    [
                        'question' => 'چرا water sorption در microfilled composites بیشتر از hybrid composites گزارش می شود؟',
                        'options' => [
                            'چون حجم فاز پلیمری در microfilled composites بیشتر است.',
                            'چون فیلرهای microfilled همگی heavy-metal oxide هستند.',
                            'چون coupling agent در hybrid composites به طور کامل حذف می شود.',
                            'چون microfilled composites به طور عمده با acid-base reaction set می شوند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: microfilled composites فاز پلیمری بیشتری دارند و به همین دلیل water sorption بالاتری از hybrid composites نشان می دهند. رد ب: heavy-metal oxide توضیح اصلی نیست. رد ج: coupling agent حذف نمی شود. رد د: microfilled composite با acid-base set نمی شود.',
                    ],
                    [
                        'question' => 'برای کلاس V ترمیمی که گزینه ها باید از گروه مواد توصیه شده فصل باشند، کدام مجموعه با جدول فصل سازگارتر است؟',
                        'options' => [
                            'Multipurpose، nanocomposite، microfilled، RMGI، compomer',
                            'Bulk filled، laboratory، provisional، cermet، QTH-cured acrylic',
                            'Flowable، core composite، laboratory composite، amalgam alloy',
                            'Nanohybrid، zinc phosphate، silicate cement، denture base resin',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: در کلاس V، multipurpose، nanocomposite، microfilled، RMGI و compomer توصیه شده اند. رد ب: bulk/laboratory/provisional/cermet/QTH acrylic مجموعه جدول کلاس V نیست. رد ج: core و laboratory برای کارکردهای دیگری اند. رد د: zinc phosphate، silicate cement و denture base resin در این جدول برای کلاس V نیامده اند.',
                    ],
                    [
                        'question' => 'کدام مجموعه، کارکردهای اصلی filler در resin composite را بهتر نشان می دهد؟',
                        'options' => [
                            'افزایش plasticizer، فعال سازی amine و کاهش shelf life',
                            'تقویت matrix، تنظیم translucency و کنترل volume shrinkage',
                            'ایجاد dentin hybrid layer، آزادسازی کلسیم و etching enamel',
                            'ایجاد pigment، مهار تمام shrinkage و حذف نیاز به curing light',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: filler ماتریکس را reinforce می کند، translucency را تنظیم می کند و volume shrinkage را کنترل می کند. رد الف: plasticizer و amine نقش filler نیستند. رد ج: hybrid layer و dentin etching به adhesion مربوط اند. رد د: filler هرچند shrinkage را کنترل می کند، آن را کاملاً حذف نمی کند و curing light را حذف نمی کند.',
                    ],
                    [
                        'question' => 'اگر خمیر visible-light initiated composite روی mixing pad زیر نور عملگر بماند، چه تغییری در حدود 60 تا 90 ثانیه ممکن است رخ دهد؟',
                        'options' => [
                            'سطح توانایی flow against tooth را از دست می دهد و کار با ماده دشوار می شود.',
                            'واکنش acid-base کامل می شود و نیاز به finishing فوری از بین می رود.',
                            'فاز air-inhibited ضخیم تر شده و کیور نهایی متوقف می شود.',
                            'water sorption به تعادل می رسد و shrinkage stress حذف می شود.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: نور عملگر می تواند within 60–90 seconds سطح paste را زود cure کند و flow against tooth را کم کند. رد ب: acid-base reaction مربوط به GI است. رد ج: متن توقف کیور نهایی را نمی گوید. رد د: تعادل hygroscopic expansion حدود روزها طول می کشد و stress را فوراً حذف نمی کند.',
                    ],
                    [
                        'question' => 'کدام تفاوت میان nanomer و nanocluster در nanofilled composite درست است؟',
                        'options' => [
                            'Nanomerها خوشه های sintered با توزیع 100 نانومتر تا submicron هستند.',
                            'Nanoclusterها ذرات غیرتجمعی تک پراکن silica یا zirconia هستند.',
                            'Nanomerها ذرات غیرagglomerated هستند و nanoclusterها از sintering سبک نانوذرات ساخته می شوند.',
                            'Nanoclusterها فقط برای ایجاد رنگدانه آهنی به ماتریکس افزوده می شوند.',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
. دلیل: nanomerها ذرات تک پراکن و غیرagglomerated silica/zirconia هستند؛ nanoclusterها با sintering سبک نانوذرات ساخته می شوند. رد الف و ب: تعریف دو گروه را جابه جا می کنند. رد د: nanocluster برای particle architecture/rheology و properties است، نه صرفاً pigment آهنی.',
                    ],
                    [
                        'question' => 'درباره thermal expansion کامپوزیت ها کدام برداشت درست تر است؟',
                        'options' => [
                            'Microfilled composites به علت polymer بیشتر، α بالاتری از fine-particle composites دارند.',
                            'α کامپوزیت ها همواره کمتر از enamel و dentin است.',
                            'Thermal expansion فقط به نوع photoinitiator وابسته است.',
                            'Composites با microfine particle به علت filler بیشتر، کمترین α را دارند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: microfilled composites به دلیل polymer بیشتر، coefficient of thermal expansion بالاتری دارند. رد ب: α کامپوزیت ها از dentin و enamel بالاتر گزارش شده است. رد ج: thermal expansion فقط به photoinitiator وابسته نیست. رد د: microfine به علت polymer بیشتر کمترین α را ندارد.',
                    ],
                    [
                        'question' => 'در chemically activated composite، آغاز polymerization در دمای اتاق با کدام مکانیسم انجام می شود؟',
                        'options' => [
                            'واکنش organic amine با organic peroxide و تولید free radicals',
                            'جذب نور آبی 465 nm توسط camphorquinone تنها',
                            'آزادسازی fluoride از FAS glass و تشکیل silica hydrogel',
                            'ring opening اکسیران توسط iodonium salt پس از LED curing',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: chemical activation در دمای اتاق با واکنش organic amine و organic peroxide برای تولید free radicals انجام می شود. رد ب: این light activation با camphorquinone است. رد ج: GI acid-base/fluoride release است. رد د: cationic silorane photoinitiation است، نه chemically activated methacrylate.',
                    ],
                    [
                        'question' => 'اگر واژه دقیق تر materials science برای اغلب direct-placed resin composites خواسته شود، کدام گزینه مناسب تر است؟',
                        'options' => [
                            'homogeneous aromatic dimethacrylate alloy',
                            'particulate-reinforced polymer matrix composite',
                            'water-based acid-base ionomer complex',
                            'hydroxyapatite-reinforced dentin-like ceramic',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: متن اصطلاح علمی مناسب را polymer matrix composite و برای direct placed دارای filler، particulate-reinforced polymer matrix composite می داند. رد الف: آلیاژ همگن نیست. رد ج: water-based ionomer complex به GIs نزدیک است. رد د: ceramic hydroxyapatite-reinforced توصیف resin composite نیست.',
                    ],
                    [
                        'question' => 'چرا nanomeric fillerها می توانند translucency و opalescence مطلوب ایجاد کنند؟',
                        'options' => [
                            'اندازه آن ها از طول موج نور مریی کوچک تر است و روی زمینه تیره blue light را ترجیحاً scatter می کنند.',
                            'اندازه آن ها از ذرات microhybrid بزرگ تر است و نور را در کل طیف جذب می کنند.',
                            'به علت وجود فلزات آزاد، رنگ yellow camphorquinone را ثابت نگه می دارند.',
                            'به علت حذف ماتریکس رزینی، light transmission بدون scattering رخ می دهد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: nanomeric fillers کوچک تر از wavelength نور مریی اند، translucency ایجاد می کنند و روی زمینه سیاه blue light را ترجیحاً scatter کرده، opalescence می دهند. رد ب: بزرگ تر بودن ذره باعث opacity/scattering بیشتر می شود. رد ج: camphorquinone yellow tint موضوع دیگری است. رد د: ماتریکس رزینی حذف نمی شود.',
                    ],
                    [
                        'question' => 'هدف مشترک بسیاری از low-shrink methacrylate monomers جدید کدام است؟',
                        'options' => [
                            'افزایش فاصله بین methacrylate groups یا افزایش stiffness monomer برای کنترل shrinkage/stress',
                            'کاهش فاصله بین double bonds برای بالا بردن cross-link density و shrinkage',
                            'جایگزینی آب به جای diluent monomer برای فعال سازی acid-base setting',
                            'حذف فیلرهای inorganic و اتکا به resin matrix خالص',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: low-shrink methacrylate monomers با افزایش فاصله بین methacrylate groups، کاهش cross-link density یا افزایش stiffness monomer برای کنترل volumetric shrinkage/stress طراحی شده اند. رد ب: کاهش فاصله و افزایش shrinkage هدف نیست. رد ج: آب و acid-base به GI مربوط است. رد د: حذف inorganic filler خلاف راهبردهای فصل است.',
                    ],
                    [
                        'question' => 'در کدام شرایط، degree of polymerization معمولاً از direct light-cured composite بیشتر است؟',
                        'options' => [
                            'when laboratory composites are postcured at elevated temperatures and light intensities',
                            'when opaque shade is cured from a distance greater than 5 mm',
                            'when chemically activated paste is left unmixed',
                            'when FAS glass is dissolved during acid-base reaction',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: laboratory composites با postcuring در temperature و light intensity بالاتر degree of polymerization بیشتری دارند. رد ب: distance زیاد و opacity cure را کم می کند. رد ج: خمیر unmixed self-cured polymerize نمی شود. رد د: dissolution FAS glass واکنش GI است.',
                    ],
                    [
                        'question' => 'درباره light-cured composite پس از تاباندن نور کدام گزاره دقیق تر است؟',
                        'options' => [
                            'ظاهر سخت ماده به معنی توقف کامل واکنش در همان لحظه است.',
                            'setting reaction تا حدود 24 ساعت ادامه می یابد، هرچند strength کافی برای finishing سریع فراهم می شود.',
                            'همه double bondها در bulk ترمیم واکنش می دهند و monomer آزاد باقی نمی ماند.',
                            'کیور سطحی فقط پس از جذب آب دهانی آغاز می شود.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: ترمیم پس از cure ظاهراً hard است، اما setting reaction تا حدود 24 ساعت ادامه دارد و strength کافی برای finishing/function فوری به دست می آید. رد الف: واکنش همان لحظه کامل متوقف نمی شود. رد ج: حدود 25% double bonds در bulk ممکن است unreacted بمانند. رد د: water uptake مربوط به compomer/GI نیست.',
                    ],
                    [
                        'question' => 'ترکیب فیلری microhybrid composites بیشتر با کدام توصیف سازگار است؟',
                        'options' => [
                            'fine particles کوچک تر به همراه microfine silica و filler loading حدود 60% تا 70% حجمی',
                            'macrofill کروی 20 تا 30 میکرومتر بدون silica microfine',
                            'تنها nanomerهای 5 تا 75 نانومتر بدون ذرات بزرگ تر',
                            'FAS glass به همراه polycarboxylic acid و آب',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: microhybrid شامل fine particles کوچک تر همراه microfine silica است و حدود 60–70% filler حجمی دارد. رد ب: macrofill اولیه است. رد ج: true nanofill بدون ذرات بزرگ تر را توصیف می کند. رد د: conventional GI است.',
                    ],
                    [
                        'question' => 'برای bonding filler به oxirane matrix در low-shrink silorane composite، کدام coupling agent مناسب تر است؟',
                        'options' => [
                            '3-methacryloxypropyltrimethoxysilane',
                            '3-glycidoxypropyltrimethoxysilane',
                            'hydroquinone',
                            'tartaric acid',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: برای oxirane matrix در silorane، 3-glycidoxypropyltrimethoxysilane استفاده می شود. رد الف: silane methacryloxy رایج برای methacrylate composites است. رد ج: hydroquinone inhibitor آکریلیک liquid است. رد د: tartaric acid در GI working/setting را کنترل می کند.',
                    ],
                    [
                        'question' => 'چرا در طراحی filler، داشتن particle-size distribution مزیت دارد؟',
                        'options' => [
                            'ذرات کوچک تر فضاهای میان ذرات بزرگ تر را پر می کنند و packing موثرتری ایجاد می شود.',
                            'ذرات کوچک تر مانع هر نوع پلیمریزاسیون free-radical می شوند.',
                            'ذرات بزرگ تر کاملاً حذف می شوند و ماده به GI تبدیل می شود.',
                            'ذرات با اندازه های مختلف، نیاز به coupling agent را از بین می برند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: توزیع diameter باعث می شود ذرات کوچک فضای بین بزرگ ترها را پر کنند و efficient packing ایجاد شود. رد ب: مانع پلیمریزاسیون free radical نیست. رد ج: حذف ذرات بزرگ و تبدیل به GI مطرح نیست. رد د: particle distribution نیاز به coupling agent را حذف نمی کند.',
                    ],
                    [
                        'question' => 'برای self-cured و dual-cured composites، کدام راهبرد نگهداری/عرضه با فصل همخوان است؟',
                        'options' => [
                            'عرضه به صورت دو سرنگ/دو خمیر و نگهداری در دمای خنک برای افزایش shelf-life',
                            'عرضه در compule شفاف و نگهداری زیر نور عملگر برای فعال سازی آهسته',
                            'عرضه به صورت powder-liquid بر پایه FAS glass و polycarboxylic acid',
                            'عرضه در gel form حاوی vinyl acetate-ethylene copolymer',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: self- و dual-cured composites به صورت دو syringe/two paste عرضه می شوند و نگهداری خنک shelf-life را افزایش می دهد. رد ب: compule شفاف زیر نور برای premature cure خطرناک است. رد ج: powder-liquid مربوط به GI است. رد د: vinyl acetate-ethylene مربوط به mouth protector است.',
                    ],
                    [
                        'question' => 'افزودن fluorescent agents به composite بیشتر برای کدام هدف است؟',
                        'options' => [
                            'جذب UV/violet و reemit در ناحیه blue برای افزایش optical vitality و اثر سفیدی ادراک شده',
                            'تبدیل visible-light curing به chemical curing و حذف camphorquinone',
                            'افزایش radiopacity تا سطح amalgam',
                            'کاهش کامل water solubility با ایجاد salt bridge',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: fluorescent agents نور UV/violet را جذب و در blue reemit می کنند تا optical vitality و whitening perceptual effect ایجاد شود. رد ب: cure chemistry را به chemical curing تبدیل نمی کند. رد ج: radiopacity با heavy atoms/fillers است. رد د: salt bridge مربوط به GI است.',
                    ],
                    [
                        'question' => 'هدف اصلی اختراع RMGIs در اواخر دهه 1980 چه بود؟',
                        'options' => [
                            'حذف fluoride release و جایگزینی کامل با wear resistance فلزی',
                            'حفظ مزایای fluoride release و clinical adhesion در کنار light curing آسان و esthetics بهتر',
                            'تبدیل تمام GIs به laboratory composites با نیاز به resin cement',
                            'جایگزینی FAS glass با quartz برای حذف acid-base reaction',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: RMGI برای حفظ fluoride release و clinical adhesion conventional GI و افزودن ease of light curing و esthetics resin-based materials معرفی شد. رد الف: fluoride release حذف نشده است. رد ج: laboratory composite نیست. رد د: FAS glass و acid-base reaction حذف نمی شوند.',
                    ],
                    [
                        'question' => 'بر اساس جدول فصل، برای cervical lesions و pediatric restorations کدام گروه ها به صورت مشترک توصیه شده اند؟',
                        'options' => [
                            'Flowable، RMGI، compomer',
                            'Laboratory، bulk filled، core',
                            'Microfilled posterior، provisional، nanohybrid',
                            'Cermet، silorane، denture base acrylic',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: برای cervical lesions و pediatric restorations در جدول flowable، RMGI و compomer مشترکاً توصیه شده اند. رد ب: laboratory/bulk/core برای کاربردهای دیگرند. رد ج: provisional و nanohybrid در این خانه های جدول نیستند. رد د: cermet/silorane/denture acrylic توصیه مشترک این دو کاربرد نیستند.',
                    ],
                    [
                        'question' => 'بیماری نیاز به restoration در ناحیه ای با نیاز همزمان به esthetics و تحمل stress دارد. کدام تحول جدیدتر در فصل برای این ترکیب هدف مناسب تر معرفی شده است؟',
                        'options' => [
                            'macrofill composites',
                            'nanocomposites',
                            'early silicate cements',
                            'conventional denture-base polymers',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: متن می گوید nanocomposites برای esthetics excellent و high mechanical properties در stress-bearing areas optimize شده اند. رد الف: macrofillهای اولیه opaque و کم مقاوم به wear بودند. رد ج: silicate cement قدیمی است. رد د: denture-base polymers برای prosthetic applications هستند.',
                    ],
                    [
                        'question' => 'کدام پیامد با hybrid و microhybrid composites، با وجود strength و wear resistance خوب، در متن آمده است؟',
                        'options' => [
                            'با گذشت زمان surface polish را از دست می دهند و rough/dull می شوند.',
                            'به علت نبود filler، در حین کیور کاملاً آب دوست می شوند.',
                            'در تمام موارد بدون bonding agent به dentin می چسبند.',
                            'به علت cermet شدن، رنگ خاکستری ثابت ایجاد می کنند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: hybrid/microhybrid با وجود wear resistance و mechanical properties خوب، با زمان polish را از دست می دهند و rough/dull می شوند. رد ب: فیلر ندارند غلط است. رد ج: self-adhesive به dentin نیستند. رد د: gray cermet به GI fused metal مربوط است.',
                    ],
                    [
                        'question' => 'علت مولکولی shrinkage در polymerization methacrylate composite کدام است؟',
                        'options' => [
                            'افزایش van der Waals volume و افزایش free volume هنگام تبدیل monomer به polymer',
                            'کاهش van der Waals volume به علت تبدیل double bond به single bond و کاهش free volume زنجیره پلیمری',
                            'تشکیل silica hydrogel اطراف FAS glass و آزادسازی fluoride',
                            'leaching باریوم و استرانسیوم از فیلرهای شیشه ای',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: shrinkage از کاهش van der Waals volume هنگام تبدیل double bond به single bond و کاهش free volume زنجیره پلیمری ناشی می شود. رد الف: جهت تغییرات برعکس است. رد ج: GI setting است. رد د: ion leaching پس از آب گیری/solubility است، نه علت اصلی polymerization shrinkage.',
                    ],
                    [
                        'question' => 'در light activation رایج methacrylate composites، کدام جفت درست است؟',
                        'options' => [
                            'camphorquinone؛ peak حدود 465 nm؛ 0.1% تا 1.0% در monomer mixture',
                            'hydroquinone؛ peak حدود 365 nm؛ 10% تا 20% در conditioner',
                            'tartaric acid؛ peak حدود 470 nm؛ عامل رنگی برای opacity',
                            'benzoyl peroxide؛ peak حدود 800 nm؛ فیلر radiopaque',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: light activation معمولاً با camphorquinone در peak حدود 465 nm و مقدار 0.1–1.0% انجام می شود. رد ب: hydroquinone inhibitor آکریلیک است. رد ج: tartaric acid در GI است. رد د: benzoyl peroxide initiator شیمیایی است، نه فیلر radiopaque.',
                    ],
                    [
                        'question' => 'کدام کارکرد coupling agent به اثرات محیط دهان مربوط تر است؟',
                        'options' => [
                            'ایجاد hydrophobic environment و کاهش water absorption کامپوزیت',
                            'افزایش عمدی water sorption برای جبران کامل shrinkage در چند ثانیه',
                            'تبدیل polymer matrix به glass hydrogel',
                            'ایجاد denture-base resilience با leaching تدریجی',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: coupling agent hydrophobic environment فراهم می کند و water absorption را کم می کند. رد ب: water sorption آهسته است و هدف افزایش عمدی آن نیست. رد ج: glass hydrogel در GI تشکیل می شود. رد د: denture-base leaching مربوط به plasticizer است.',
                    ],
                    [
                        'question' => 'کدام راهبردها در متن برای کاهش polymerization contraction در methacrylate composites ذکر شده اند؟',
                        'options' => [
                            'prepolymerized resin، افزایش inorganic filler، و استفاده از high molecular mass methacrylate monomers',
                            'حذف فیلر، افزودن آب، و کاهش وزن مولکولی monomerها',
                            'استفاده از tartaric acid، FAS glass، و polycarboxylic acid',
                            'افزودن polyethylene بیشتر، vacuum forming، و laminate mouthguard',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: متن راهبردهای کاهش contraction را prepolymerized resin، maximizing inorganic filler و high molecular mass methacrylate monomers ذکر می کند. رد ب: خلاف متن است. رد ج: اجزای GI هستند. رد د: mouthguard/denture topics هستند.',
                    ],
                    [
                        'question' => 'اگر light intensity یا duration کافی نباشد، به ویژه در عمق زیاد، کدام نتیجه برای composite محتمل تر است؟',
                        'options' => [
                            'polymerization ناکافی، افزایش water sorption و solubility، و احتمال color instability زودرس',
                            'تشکیل فوری calcium-polyacrylate bond و افزایش fluoride recharge',
                            'کاهش تمام ion leaching به صفر و افزایش hardness تا سطح enamel',
                            'تبدیل flowable composite به self-adhesive RMGI',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: inadequate light intensity/duration به ناکافی بودن polymerization، افزایش water sorption/solubility و color instability زودرس می انجامد. رد ب: calcium-polyacrylate bond مربوط به GI adhesion است. رد ج: hardness تا سطح enamel نمی رسد و leaching صفر نمی شود. رد د: flowable به RMGI تبدیل نمی شود.',
                    ],
                    [
                        'question' => 'کدام محدودیت بالینی برای silorane composites با وجود کاهش shrinkage/stress باقی می ماند؟',
                        'options' => [
                            'باید به علت limited depth of cure به صورت increments قرار داده شوند و adhesive اختصاصی لازم است.',
                            'نباید با هیچ نوع filler استفاده شوند چون تمام fillerها آن ها را پایدار می کنند.',
                            'فقط به صورت chemically activated با amine-peroxide قابل کیور هستند.',
                            'به علت نبود hydrophobicity، برای محیط دهان ممنوع اند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: silorane با وجود lower shrinkage/stress، به علت limited depth of cure باید in increments قرار گیرد و adhesive اختصاصی لازم دارد. رد ب: filler باید با دقت انتخاب شود و residual basicity می تواند instability دهد. رد ج: light-cured cationic initiation دارد. رد د: siloxane hydrophobicity می دهد.',
                    ],
                    [
                        'question' => 'air-inhibited layer در light-cured methacrylate composite از نظر layering چه اهمیتی دارد؟',
                        'options' => [
                            'برای اضافه کردن increment بعدی مفید است، چون سطح کاملاً پلیمریزه نشده باقی می ماند.',
                            'نشانه شکست کامل initiator است و باید تمام ترمیم خارج شود.',
                            'سبب می شود تمام double bondهای bulk به 100% conversion برسند.',
                            'فقط در GIs تشکیل می شود و به کامپوزیت ارتباطی ندارد. نیمه دوم فصل 9 — سوال های 41 تا 80',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: air-inhibited unpolymerized surface layer برای subsequent incremental placement مفید است. رد ب: failure initiator نیست. رد ج: تمام double bonds به 100% conversion نمی رسند. رد د: مربوط به methacrylate composite surface است، نه GI.',
                    ],
                ],
            ],
            [
                'slug' => '9-2',
                'path' => '/exams/craig-dental-materials/6-10/9-2/',
                'questionCount' => 40,
                'label' => 'فصل ۹ - قسمت دوم',
                'title' => 'آزمون فصل ۹ - قسمت دوم',
                'subtitle' => '۴۰ سؤال چهارگزینه‌ای از مجموعه کریگ.',
                'description' => 'مرور ۴۰ سؤال از مجموعه کریگ.',
                'eyebrow' => 'کریگ | فصول ۶ تا ۱۰ | فصل ۹ - قسمت دوم',
                'backHref' => '/exams/craig-dental-materials/6-10/',
                'backLabel' => 'بازگشت به فصول ۶ تا ۱۰ کریگ',
                'autoAdvance' => true,
                'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'siteSubtitle' => 'آزمون‌ها',
                'siteBadge' => 'آزمون رفرنسی',
                'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'questions' => [
                    [
                        'question' => 'در انتخاب کامپوزیت برای ترمیم posterior، چرا فصل بر controlled clinical studies بیش از مقایسه مستقیم همه in vitro wear tests تاکید می کند؟',
                        'options' => [
                            'چون روش های in vitro wear متعدد و غیرقابل استانداردسازی کامل اند و مقایسه مستقیم با عملکرد بالینی ندارند.',
                            'چون wear در posterior فقط به رنگ و fluorescence وابسته است.',
                            'چون در posterior هیچ contact occlusal یا lateral excursion وجود ندارد.',
                            'چون تمام کامپوزیت های جدید بدون تفاوت wear مشابه amalgam دارند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: wear in vitro روش های متعددی دارد و direct comparison با clinical performance استاندارد نشده؛ فصل برای posterior به controlled clinical studies توصیه می کند. رد ب: wear فقط به color/fluorescence مربوط نیست. رد ج: posterior نیرو و lateral contacts بیشتری دارد. رد د: تفاوت بین composites وجود دارد.',
                    ],
                    [
                        'question' => 'هنگام bonding composite به dentin، کدام ساختارها در توصیف فصل دیده می شوند؟',
                        'options' => [
                            'silica hydrogel و unreacted FAS particles',
                            'adhesive layer، hybrid layer و resin tags',
                            'tartaric acid layer، zinc oxide layer و capillary fibers',
                            'ceramic coping، wax spacer و gypsum die',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
. دلیل: در bonding به dentin، composite، adhesive layer، hybrid layer و resin tags توصیف شده اند. رد الف: silica hydrogel و FAS مربوط به GI است. رد ج: tartaric acid/zinc oxide/capillary fibers به این interface مربوط نیست. رد د: مربوط به indirect restoration workflow است.',
                    ],
                    [
                        'question' => 'بیمار high-caries activity با ضایعه کوچک و margin روی dentin دارد و isolation دشوار است. کدام انتخاب از منطق فصل دفاع پذیرتر است؟',
                        'options' => [
                            'Conventional GI یا RMGI',
                            'Laboratory composite بدون fluoride release',
                            'Macrofilled composite با ذرات 30 μm',
                            'Heat-cured PMMA denture resin',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: GI/RMGI برای high caries activity، small lesions، margins روی dentin و isolation دشوار مناسب اند و fluoride release دارند. رد ب: laboratory composite fluoride/self-adhesion ندارد. رد ج: macrofill اولیه opaque و wear ضعیف دارد. رد د: PMMA denture resin برای چنین filling نیست.',
                    ],
                    [
                        'question' => 'در کیور کامپوزیت light-cured، اگر ترمیم large surface دارد، کدام اقدام برای پوشش کامل سطح توصیه می شود؟',
                        'options' => [
                            'حرکت stepwise نور روی سطح، چون beam به اندازه کافی فراتر از قطر tip پخش نمی شود.',
                            'قرار دادن tip با فاصله زیاد برای افزایش uniformity و کاهش نیاز به exposure',
                            'استفاده از shade opaque و کاهش زمان به 5 ثانیه',
                            'تبدیل ماده به chemically activated با افزودن polyacrylic acid',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: چون beam به اندازه کافی فراتر از diameter tip پخش نمی شود، باید نور به صورت step across surface داده شود. رد ب: فاصله زیاد intensity را کم می کند. رد ج: opaque shade و زمان کوتاه cure ناکافی می دهد. رد د: polyacrylic acid composite را chemically activated نمی کند.',
                    ],
                    [
                        'question' => 'کدام ادعا درباره biocompatibility کامپوزیت ها دقیق تر است؟',
                        'options' => [
                            'bulk monomerهای اصلی in vitro cytotoxic هستند، اما ریسک cured composite به میزان release وابسته است.',
                            'cured composite هیچ component آزاد نمی کند و بنابراین نیازی به بررسی toxicity ندارد.',
                            'dentin barrier انتقال components را افزایش می دهد و تماس pulpal را بیشتر می کند.',
                            'direct pulp capping با composite کم خطرتر از حالتی است که dentin barrier وجود دارد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: bulk monomers مثل Bis-GMA/TEGDMA/UDMA in vitro cytotoxic هستند، اما خطر cured composite به release و cure efficiency وابسته است. رد ب: cured composite می تواند مقدار کمی component release کند. رد ج: dentin barrier انتقال به pulp را کم می کند. رد د: direct pulp-capping با نبود barrier پرخطرتر است.',
                    ],
                    [
                        'question' => 'در bulk fill composites، کدام ترکیب ویژگی/محدودیت درست است؟',
                        'options' => [
                            'لایه گذاری تا حدود 4 mm، translucency بیشتر، ولی محدوده shade محدودتر',
                            'نیاز اجباری به لایه های 0.5 mm، opacity بیشتر، و absence of initiator',
                            'کاربرد اصلی فقط denture base، با heat-cured PMMA',
                            'setting فقط با acid-base reaction و fluoride recharge بالا',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: bulk fillها برای لایه های تا 4 mm، translucency بیشتر و depth of cure بیشتر ساخته شده اند، ولی به علت translucency limitations shade range محدود است. رد ب: 0.5 mm و absence of initiator غلط است. رد ج: denture base نیست. رد د: acid-base/fluoride recharge توصیف GI است.',
                    ],
                    [
                        'question' => 'چرا RMGI به عنوان liner یا base زیر resin composite در sandwich restorations جذاب است؟',
                        'options' => [
                            'modulus آن در initial light set پایین است و با تکمیل acid-base reaction بالا می رود، بنابراین می تواند بخشی از shrinkage stress کامپوزیت را relieve کند.',
                            'modulus آن از ابتدا مشابه enamel است و هیچ تغییر زمانی ندارد.',
                            'به علت نبود resin component، همیشه سخت تر از composite است.',
                            'چون prior phosphoric acid etching عمیق، collagen collapse را الزامی می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: RMGI ابتدا modulus پایین دارد و با acid-base maturation افزایش می یابد، بنابراین در liner/base زیر composite می تواند shrinkage stress را relieve کند. رد ب: modulus ثابت نیست. رد ج: RMGI resin component دارد و سخت تر از composite نیست. رد د: prior phosphoric acid etching لازم نیست.',
                    ],
                    [
                        'question' => 'کدام توصیف درباره compomers درست تر است؟',
                        'options' => [
                            'آب در فرمول اولیه ندارند؛ عمدتاً با light-cured polymerization set می شوند و acid-base reaction پس از جذب آب رخ می دهد.',
                            'به صورت powder-liquid FAS glass و polycarboxylic acid بدون resin monomer عرضه می شوند.',
                            'نسبت به GIs fluoride بیشتری آزاد می کنند و recharge بیشتری دارند.',
                            'بدون bonding agent به tooth structure متصل می شوند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: compomers بدون آب فرموله می شوند؛ setting عمدتاً light-cured polymerization است و acid-base reaction پس از آب گیری در saliva رخ می دهد. رد ب: conventional GI را توصیف می کند. رد ج: fluoride release/recharge کمتر از GI و hybrid ionomer است. رد د: برای bond به bonding agent نیاز دارند.',
                    ],
                    [
                        'question' => 'برای surface pretreatment در bonding composite به silica-based ceramic، کدام primer در فصل ذکر شده است؟',
                        'options' => [
                            'silane primer',
                            'acidic phosphate monomer',
                            'alloy primer',
                            'polyacrylic acid conditioner',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: برای silica-based ceramics، silane primer در متن آمده است. رد ب: acidic phosphate monomer برای zirconia است. رد ج: alloy primer برای alloys است. رد د: polyacrylic acid conditioner برای GI tooth pretreatment است.',
                    ],
                    [
                        'question' => 'در glass ionomer conventional، کدام رخداد در مراحل اولیه setting با سرعت بیشتری رخ می دهد؟',
                        'options' => [
                            'اتصال calcium ions به polyacrylate chains',
                            'اتصال aluminum ions به عنوان نخستین یون غالب',
                            'polymerization کامل HEMA با light',
                            'cross-link شدن glycol dimethacrylate در PMMA',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: در conventional GI، calcium ions سریع تر به polyacrylate chains متصل می شوند و aluminum binding دیرتر رخ می دهد. رد ب: ترتیب را برعکس می کند. رد ج: HEMA light polymerization مربوط به RMGI است. رد د: glycol dimethacrylate PMMA cross-linker است.',
                    ],
                    [
                        'question' => 'چرا استفاده از composite به عنوان direct pulp-capping agent در فصل پرریسک تر دانسته شده است؟',
                        'options' => [
                            'چون dentin barrier وجود ندارد تا exposure پالپ به components آزادشده را محدود کند.',
                            'چون composite در حضور dentin هیچ component آزاد نمی کند.',
                            'چون fluoride release آن چندین برابر RMGI است.',
                            'چون surface pretreatment با 10% تا 20% polyacrylic acid آن را خنثی می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: direct pulp capping با composite ریسک بیشتری دارد چون dentin barrier وجود ندارد تا pulp exposure به components آزادشده را محدود کند. رد ب: composite ممکن است component release کند. رد ج: fluoride release ویژگی اصلی composite نیست. رد د: polyacrylic acid برای GI pretreatment است.',
                    ],
                    [
                        'question' => 'برای core build-up composite، کدام نکته کاربردی در فصل برجسته شده است؟',
                        'options' => [
                            'retention نهایی crown نباید فقط بر composite core تکیه کند، چون adhesion به dentin تنها برای مقاومت در برابر rotation/dislodgement کافی نیست.',
                            'composite core نسبت به amalgam دیرتر finishing می شود و contouring دشوارتر است.',
                            'همه self-cured core materials با همه light-cured bonding agents کاملاً سازگارند.',
                            'color tint در core composite ممنوع است چون تشخیص از tooth structure را دشوار می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: فصل تاکید می کند retention final restoration نباید فقط بر composite core باشد؛ adhesion به dentin alone برای rotation/dislodgement کافی نیست. رد ب: composite core فوری finish می شود و contouring آسان دارد. رد ج: برخی self-cured core materials با برخی light-cured bonding agents ناسازگارند. رد د: tint برای contrast با tooth structure استفاده می شود.',
                    ],
                    [
                        'question' => 'کدام ویژگی QTH light-curing units با ساختار آن ها هماهنگ است؟',
                        'options' => [
                            'broad-spectrum bulb، blue bandpass filter، UV filter، reflector، fan و light guide',
                            'p-n junction gallium nitride بدون فیلتر و بدون نیاز به reflector',
                            'acid-base initiator، FAS glass و powder-liquid capsule',
                            'vinyl acetate-ethylene sheet و vacuum forming',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: QTH شامل broad-spectrum bulb، filters، reflector، fan، power supply و light guide است. رد ب: LED را توصیف می کند. رد ج: GI setting است. رد د: mouth protector fabrication است.',
                    ],
                    [
                        'question' => 'کدام شاخص عددی برای clinical acceptance posterior resin composites در جدول فصل آمده است؟',
                        'options' => [
                            'wear بین 6 و 18 ماه بیش از 50 μm نباشد.',
                            'failure در 18 ماه تا 25% قابل قبول است.',
                            'marginal integrity در 18 ماه تا 30% تخریب را مجاز می داند.',
                            'recurrent/marginal caries در 18 ماه تا 20% مجاز است.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: جدول ADA proposed criteria می گوید wear بین 6 و 18 ماه نباید بیش از 50 μm باشد. رد ب: failure باید no more than 5% باشد. رد ج: marginal integrity degradation no more than 5% است. رد د: recurrent/marginal caries no more than 5% است.',
                    ],
                    [
                        'question' => 'علت radiopacity در nanofilled composite با توضیح فصل کدام است؟',
                        'options' => [
                            'استفاده از nanomeric zirconia یا وارد کردن zirconia در nanoclusterها همراه silica',
                            'استفاده از silica خالص بدون ذرات zirconia یا barium',
                            'وجود camphorquinone زردرنگ در خمیر uncured',
                            'افزودن nylon fibers برای شبیه سازی capillaryها',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: radiopacity در nanofilled composite با nanomeric zirconia یا zirconia در nanoclusterها همراه silica ایجاد می شود. رد ب: silica خالص radiopaque نیست. رد ج: camphorquinone زردرنگ photoinitiator است. رد د: nylon/acrylic fibers برای شبیه سازی capillaries در denture base است.',
                    ],
                    [
                        'question' => 'کدام عبارت درباره microfilled composites درست است؟',
                        'options' => [
                            'برای class 3 و class 5 کم استرس با نیاز به polish و esthetics بالا توصیه می شوند.',
                            'برای bulk cure تا 4 mm در کلاس 1 و 2 طراحی شده اند.',
                            'به علت filler بسیار زیاد، water sorption و thermal expansion کمتری از nanocomposites دارند.',
                            'فقط به صورت powder-liquid و acid-base setting عرضه می شوند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: microfilled composites برای class 3 و class 5 کم استرس که polish و esthetics اهمیت دارند توصیه شده اند. رد ب: bulk fill را توصیف می کند. رد ج: به علت filler کمتر، water sorption/thermal expansion بالاتر از microhybrid/nanocomposite دارند. رد د: powder-liquid acid-base مربوط به GI است.',
                    ],
                    [
                        'question' => 'بیمار با cervical abfraction lesion نیاز به ماده ای با modulus پایین دارد. کدام گزینه با فصل همخوان تر است؟',
                        'options' => [
                            'Flowable/syringeable composite',
                            'Macrofill composite اولیه',
                            'Laboratory composite fiber-reinforced',
                            'Heat-cured PMMA denture base',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: flowable/syringeable composites modulus پایین دارند و می توانند برای cervical abfraction areas مفید باشند. رد ب: macrofill اولیه برای این خصوصیت مطرح نیست. رد ج: laboratory composite برای indirect prosthetic devices است. رد د: PMMA denture base برای abfraction restoration نیست.',
                    ],
                    [
                        'question' => 'در RMGI واقعی light-cure، اگر فرمول آب کم و methacrylate زیاد داشته باشد، کدام واکنش آسیب می بیند؟',
                        'options' => [
                            'acid-base reaction به علت کاهش ionization polycarboxylic acid',
                            'methacrylate polymerization به علت نبود double bond',
                            'blue LED emission به علت نبود gallium nitride',
                            'peroxide decomposition در PMMA powder',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: اگر water کم و methacrylate زیاد باشد، ionization polycarboxylic acid suppressed می شود و acid-base reaction کم رخ می دهد. رد ب: methacrylate double bonds وجود دارند. رد ج: LED emission مسیله فرمول RMGI نیست. رد د: peroxide decomposition مربوط به PMMA acrylic resin است.',
                    ],
                    [
                        'question' => 'کدام جفت درباره LED curing units درست تر است؟',
                        'options' => [
                            'p-n junctionهای gallium nitride؛ خروجی 450 تا 490 nm؛ عدم نیاز به فیلتر',
                            'tungsten filament؛ خروجی broad-spectrum؛ blue bandpass filter الزامی',
                            'FAS glass؛ acid-base reaction؛ self-adhesive setting',
                            'peroxide initiator؛ hydroquinone؛ heat-cured denture base',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: LEDها junction doped semiconductor based on gallium nitride دارند، 450–490 nm emit می کنند و filter لازم ندارند. رد ب: QTH است. رد ج: GI است. رد د: acrylic denture base chemistry است.',
                    ],
                    [
                        'question' => 'در conventional GI، نقش water کدام مجموعه است؟',
                        'options' => [
                            'ion transport برای acid-base reaction و fluoride release، bound water برای stability، و plasticity حین manipulation',
                            'فقط ایجاد رنگدانه و جلوگیری از تمام ion exchange',
                            'آغاز free-radical methacrylate polymerization بدون initiator',
                            'کاهش fluoride release و حذف silica hydrogel',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: water در GI برای ion transport و fluoride release، بخشی به صورت bound water برای stability، و plasticity در manipulation ضروری است. رد ب: فقط pigment نیست. رد ج: free-radical methacrylate polymerization با آب شروع نمی شود. رد د: water fluoride release را حذف نمی کند.',
                    ],
                    [
                        'question' => 'کدام گزینه درباره adhesion GIs به tooth structure صحیح تر است؟',
                        'options' => [
                            'adhesion عمدتاً شیمیایی و بر پایه ion exchange است، و phosphoric acid etching لازم نیست.',
                            'adhesion فقط micromechanical و مشابه etched enamel با resin adhesive است.',
                            'GIها برای adhesion همیشه به 50-μm alumina sandblasting نیاز دارند.',
                            'adhesion فقط پس از silane primer روی FAS glass در cavity رخ می دهد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: GIs self-adhesive هستند؛ adhesion عمدتاً chemical ion exchange است و phosphoric acid etching لازم نیست. رد ب: برخلاف resin bonding، تنها micromechanical نیست. رد ج: sandblasting 50 μm برای substrates دیگر در composite repair/bonding است. رد د: silane primer مکانیسم اصلی GI-tooth adhesion نیست.',
                    ],
                    [
                        'question' => 'برای laboratory composites، کدام توجیه بالینی درست تر است؟',
                        'options' => [
                            'در حفره های بزرگ با C-factor بالا، indirect prefabricated composite می تواند نگرانی های polymerization stress و microleakage را کاهش دهد.',
                            'چون در دهان بدون adhesive به tooth preparation جوش می خورد.',
                            'چون ultimate mechanical properties آن ها همیشه از ceramics بالاتر است.',
                            'چون فقط برای low-caries risk و without resin cement استفاده می شوند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: در حفره های بزرگ با C-factor بالا، indirect laboratory composite به کاهش دغدغه polymerization stress، microleakage و sensitivity کمک می کند. رد ب: همچنان adhesives/resin cement لازم است. رد ج: ultimate mechanical properties از ceramics بالاتر نیست. رد د: restorations معمولاً با resin cements bonded می شوند.',
                    ],
                    [
                        'question' => 'کدام فرایند، conventional GI را به مرور قوی تر می کند؟',
                        'options' => [
                            'ادامه ionic reaction و maturation پس از initial set',
                            'تبخیر کامل water و حذف bound water',
                            'leaching تدریجی تمام fluoride به همراه افت strength',
                            'curing با LED 1000 تا 1400 mW/cm2',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: conventional GI initial set در 3–4 دقیقه دارد، اما ionic reaction حداقل 24 ساعت یا بیشتر ادامه می یابد و maturation strength را افزایش می دهد. رد ب: bound water برای stability مهم است و حذف کامل آن مطلوب نیست. رد ج: متن loss of strength در سال ها storage in water را گزارش نمی کند. رد د: LED cure مربوط به RMGI/composite است.',
                    ],
                    [
                        'question' => 'کدام ترکیب کاربردی برای provisional restorations مطابق فصل درست است؟',
                        'options' => [
                            'حفظ موقعیت prepared tooth، sealing/insulation، محافظت margin و ارزیابی candidates برای esthetic replacement',
                            'ایجاد fluoride reservoir طولانی مدت و remineralization مستقیم حفره های deep',
                            'جذب 80% تا 90% انرژی ضربه در ورزش',
                            'تشکیل calcium-polyacrylate bond با dentin بدون bonding agent',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: provisional restorations موقعیت دندان، sealing/insulation، محافظت margins، vertical dimension و ارزیابی درمان/esthetic candidates را حفظ می کنند. رد ب: GI liner/base را توصیف می کند. رد ج: mouth protector است. رد د: GI adhesion است.',
                    ],
                    [
                        'question' => 'در bulk fill composites، کدام راهبرد برای کاهش shrinkage stress در متن آمده است؟',
                        'options' => [
                            'stress-relieving monomer، addition-fragmentation monomer، یا fillers با low elastic modulus',
                            'افزایش opacifierها تا cure depth به 1 mm برسد',
                            'حذف تمام initiatorها و تکیه بر saliva water uptake',
                            'افزودن silver fused glass و ایجاد cermet خاکستری',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: برای bulk fillها special stress-relieving monomer، addition-fragmentation monomer و fillers با low elastic modulus برای جذب stress ذکر شده اند. رد ب: افزایش opacifier depth of cure را کم می کند. رد ج: initiator حذف نمی شود. رد د: cermet مربوط به conventional GI fused with metal است.',
                    ],
                    [
                        'question' => 'کدام وضعیت درباره Knoop hardness کامپوزیت ها درست است؟',
                        'options' => [
                            'مقدار آن از enamel و amalgam کمتر است و در fine-particle composites از microfine composites بیشتر می شود.',
                            'مقدار آن همیشه از enamel بیشتر و از amalgam کمتر است.',
                            'در کامپوزیت های با filler بزرگ، indentation همیشه نماینده کل ماده است.',
                            'تفاوت filler volume هیچ اثری بر آن ندارد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: Knoop hardness کامپوزیت ها (22–80 kg/mm2) کمتر از enamel و amalgam است و fine-particle composites به علت filler hardness/volume fraction، سختی بیشتری از microfine نشان می دهند. رد ب: از enamel بیشتر نیست. رد ج: در fillers بزرگ، indentation ممکن است فقط روی یک فاز بیفتد و misleading باشد. رد د: filler volume اثر دارد.',
                    ],
                    [
                        'question' => 'کدام مورد از مزایای composite core نسبت به amalgam در فصل نیست؟',
                        'options' => [
                            'bonded شدن به dentin',
                            'finished شدن فوری',
                            'easy contouring',
                            'اتکا کامل crown retention به core بدون tooth structure باقی مانده',
                        ],
                        'correctIndex' => 3,
                        'explanation' => '**پاسخ درست:** گزینه د
. دلیل: تکیه کامل crown retention به composite core مزیت نیست و متن آن را هشدار می دهد. رد الف، ب و ج: bonded شدن به dentin، finishing فوری و easy contouring از مزایای composite cores نسبت به amalgam ذکر شده اند.',
                    ],
                    [
                        'question' => 'کدام علت ها برای کاهش postoperative sensitivity با RMGI مطرح شده اند؟',
                        'options' => [
                            'عدم نیاز به prior etching و جلوگیری از collagen collapse، همراه با dual-setting و modulus build-up تدریجی برای جذب stress',
                            'etching طولانی phosphoric acid و حذف کامل smear layer بدون primer',
                            'release نکردن هیچ جزء و polymerization آنی با modulus بالا',
                            'نبود water و توقف کامل acid-base reaction',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: RMGI prior etching لازم ندارد، بنابراین demineralized/collapsed collagen layer ایجاد نمی شود؛ dual-setting و modulus build-up تدریجی نیز contraction stress را کم می کند. رد ب: etching طولانی لازم نیست. رد ج: modulus از ابتدا بالا نیست و release/setting وجود دارد. رد د: آب برای RMGI/GI مهم است.',
                    ],
                    [
                        'question' => 'در compomers، چرا fluoride release و recharge کمتر از GI و hybrid ionomer است؟',
                        'options' => [
                            'چون مقدار GI موجود در compomers کمتر است.',
                            'چون فیلر آن ها فقط از فلزات نجیب تشکیل شده است.',
                            'چون اصلاً silicate glass ندارند و آب جذب نمی کنند.',
                            'چون bonding agent تمام fluoride را غیرفعال می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: چون مقدار GI در compomerها کمتر است، fluoride release و duration و recharge کمتر از glass و hybrid ionomers است. رد ب: فیلرها فلز نجیب نیستند. رد ج: fluoride-releasing silicate glass دارند و آب پس از placement جذب می کنند. رد د: bonding agent علت اصلی کاهش fluoride نیست.',
                    ],
                    [
                        'question' => 'کدام عامل در کاهش intensity نور QTH و راه حل نگهداری آن درست جفت شده است؟',
                        'options' => [
                            'resin deposit on light tip — clean or replace light tip',
                            'burn-out of bulb filament — overlap curing on larger surface',
                            'increased distance of tip — get built-in voltage regulator',
                            'dust or deterioration of reflector — replace bulb only',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: resin deposit on light tip با clean or replace light tip مدیریت می شود. رد ب: burn-out bulb filament با replace bulb حل می شود. رد ج: increased distance با keep light tip close to material اصلاح می شود. رد د: dust/deterioration reflector با clean or replace reflector اصلاح می شود.',
                    ],
                    [
                        'question' => 'در denture base acrylic powder، کدام جزء برای شروع polymerization مونومر پس از افزودن liquid ذکر شده است؟',
                        'options' => [
                            'benzoyl peroxide یا diisobutylazonitrile',
                            'hydroquinone',
                            'tartaric acid',
                            'camphorquinone',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: powder acrylic resin حاوی initiator مانند benzoyl peroxide یا diisobutylazonitrile است تا پس از افزودن monomer liquid polymerization را initiate کند. رد ب: hydroquinone inhibitor liquid است. رد ج: tartaric acid GI setting modifier است. رد د: camphorquinone photoinitiator composite است.',
                    ],
                    [
                        'question' => 'کدام عامل در denture base acrylic liquid برای جلوگیری از premature polymerization افزوده می شود؟',
                        'options' => [
                            'hydroquinone',
                            'FAS glass',
                            'polyacrylic acid 20%',
                            'alumina whisker',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: hydroquinone برای جلوگیری از premature polymerization در liquid استفاده می شود. رد ب: FAS glass مربوط به GI است. رد ج: polyacrylic acid conditioner برای GI tooth surface است. رد د: alumina whisker برای reinforcement powder است، نه inhibitor.',
                    ],
                    [
                        'question' => 'کدام عبارت درباره plasticizers در acrylic denture base درست است؟',
                        'options' => [
                            'plasticizerهای خارجی مانند dibutyl phthalate واکنش پلیمریزاسیون نمی دهند و با leaching موجب hardening می شوند.',
                            'plasticizerها همیشه وارد زنجیره پلیمری می شوند و leaching ندارند.',
                            'plasticizerها همان cross-linking agents هستند و crazing را کاهش می دهند.',
                            'plasticizerها باعث افزایش فوری fluoride release از PMMA می شوند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: external plasticizers مانند dibutyl phthalate واکنش نمی دهند، بین polymer molecules تداخل ایجاد می کنند و با leaching در oral fluids باعث hardening می شوند. رد ب: internal plasticizing با butyl/octyl methacrylate leach out نمی کند، اما همه plasticizerها چنین نیستند. رد ج: cross-linkers متفاوت اند. رد د: PMMA fluoride release ندارد.',
                    ],
                    [
                        'question' => 'اگر goal کاهش surface crazing و احتمالاً solubility/water sorption در acrylic plastic باشد، کدام افزودنی منطقی تر است؟',
                        'options' => [
                            'glycol dimethacrylate به عنوان cross-linking compound',
                            'hydroquinone به عنوان opacifier',
                            'tartaric acid به عنوان initiator',
                            'vinyl acetate-ethylene به عنوان photoinitiator',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: glycol dimethacrylate cross-linker است و resistance به crazing را افزایش و solubility/water sorption را ممکن است کاهش دهد. رد ب: hydroquinone inhibitor است نه opacifier. رد ج: tartaric acid GI additive است. رد د: vinyl acetate-ethylene mouth protector sheet است.',
                    ],
                    [
                        'question' => 'در mouth protector سفارشی، کدام ماده و ویژگی سختی با فصل سازگار است؟',
                        'options' => [
                            'vinyl acetate-ethylene copolymer؛ هرچه polyethylene بیشتر باشد، سخت تر است.',
                            'FAS glass-polycarboxylic acid؛ هرچه water بیشتر باشد، سخت تر است.',
                            'Bis-GMA/UDMA composite؛ هرچه camphorquinone کمتر باشد، سخت تر است.',
                            'PMMA powder-liquid؛ هرچه hydroquinone بیشتر باشد، soft layer ایجاد می شود.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: custom mouth protector sheets عمدتاً vinyl acetate-ethylene copolymers هستند و copolymers با polyethylene بیشتر سخت ترند. رد ب: GI material است. رد ج: composite photoinitiator سختی mouthguard را توضیح نمی دهد. رد د: hydroquinone inhibitor است و soft layer ایجاد نمی کند.',
                    ],
                    [
                        'question' => 'کدام عبارت درباره مکانیسم محافظتی mouth protector درست است؟',
                        'options' => [
                            'مانند shock absorber عمل می کند، 80% تا 90% انرژی ضربه را جذب می کند و باقی مانده را در کل arch توزیع می کند.',
                            'با افزایش occlusal stress در ناحیه برخورد از حرکت دندان ها جلوگیری می کند.',
                            'با آزادسازی fluoride، trauma را به طور شیمیایی کاهش می دهد.',
                            'با polymerization shrinkage، انرژی ضربه را در interface حبس می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: mouth protector مانند shock absorber عمل می کند، 80–90% انرژی ضربه را جذب و باقی را یکنواخت به کل arch توزیع می کند. رد ب: هدف افزایش occlusal stress نیست. رد ج: fluoride release مکانیسم محافظت ضربه ای نیست. رد د: shrinkage interface مکانیسم mouth protector نیست.',
                    ],
                    [
                        'question' => 'کدام مقایسه بین انواع mouth protector درست تر است؟',
                        'options' => [
                            'custom-made از نظر fit، comfort، ease of speaking و durability بهتر است، اما به علت هزینه بالاتر کمتر از stock یا mouth-formed رایج است.',
                            'stock از نظر fit و comfort بهتر از custom-made است و هزینه بالاتری دارد.',
                            'mouth-formed همیشه از custom-made durableتر است و سخن گفتن را آسان تر می کند.',
                            'custom-made به علت نبود محافظت ورزشی توصیه نمی شود.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: custom-made protectors از نظر fit، comfort، ease of speaking و durability عالی اند، اما به علت higher cost کمتر از stock/mouth-formed رایج اند. رد ب و ج: کیفیت stock و mouth-formed در این موارد ضعیف تا متوسط است، نه بهتر از custom. رد د: custom-made protective و توصیه شده است.',
                    ],
                    [
                        'question' => 'در single-unit automix dispensing device برای RMGI دوخمیری، کدام مزیت/سازوکار ذکر شده است؟',
                        'options' => [
                            'دو paste در دو compartment کنار هم قرار دارند و nozzle دارای ministatic mixer است؛ احتمال microbubble کمتر می شود.',
                            'powder و liquid با قاشق و قطره چکان جدا می شوند و hand spatulation ضروری است.',
                            'تمام مخلوط با ultrasonic scaler فعال می شود و به amalgamator نیازی ندارد.',
                            'تنها برای PMMA mouth protector به کار می رود.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: automix capsule دو paste side-by-side و nozzle دارای ministatic mixer دارد؛ گفته شده microbubbles کمتری ایجاد می کند. رد ب: powder-liquid hand spatulation روش قدیمی تر است. رد ج: ultrasonic scaler نقش ندارد. رد د: برای RMGI two-paste است نه PMMA mouthguard.',
                    ],
                    [
                        'question' => 'کدام گزاره درباره fluoride release در GIs/RMGIs دقیق تر است؟',
                        'options' => [
                            'fluoride به صورت sustained و از طریق ion-exchange آزاد می شود و ماده می تواند fluoride را از dentifrices و mouthwashes جذب کند.',
                            'fluoride فقط در لحظه اول mixing آزاد می شود و سپس reservoir بودن از بین می رود.',
                            'release باعث کاهش قدرت cement در سال های storage in water می شود.',
                            'fluoride release در compomerها از GI و RMGI طولانی تر و بیشتر است.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: GIs/RMGIs fluoride را sustained و با ion-exchange آزاد می کنند و می توانند از dentifrices، mouthwashes و topical fluoride solutions fluoride بگیرند. رد ب: release فقط لحظه اول نیست. رد ج: متن loss of strength with time را در صورت فرمولاسیون مناسب رد می کند. رد د: compomerها fluoride کمتر و recharge کمتر دارند.',
                    ],
                    [
                        'question' => 'اگر RMGI فقط photoinitiator داشته باشد، محدودیت اصلی در کاربرد bulk restorative چیست و tri-cure چگونه آن را برطرف می کند؟',
                        'options' => [
                            'light penetration محدود است و باید لایه گذاری شود؛ tri-cure با افزودن redox self-cure امکان methacrylate polymerization بدون نور را فراهم می کند.',
                            'acid-base reaction خیلی سریع است و tri-cure آن را با حذف آب متوقف می کند.',
                            'fluoride release زیاد است و tri-cure با حذف FAS glass آن را کم می کند.',
                            'material فقط برای mouthguard مناسب است و tri-cure آن را به PMMA تبدیل می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
. دلیل: در RMGI فقط photoinitiated، محدودیت penetration light باعث نیاز به layer curing می شود؛ tri-cure با self-cure redox initiators polymerization methacrylate را در absence of light ممکن می کند. رد ب: tri-cure آب را حذف نمی کند. رد ج: FAS glass حذف نمی شود. رد د: tri-cure RMGI را به PMMA mouthguard تبدیل نمی کند.',
                    ],
                ],
            ],
            [
                'slug' => '10-1',
                'path' => '/exams/craig-dental-materials/6-10/10-1/',
                'questionCount' => 40,
                'label' => 'فصل ۱۰ - قسمت اول',
                'title' => 'آزمون فصل ۱۰ - قسمت اول',
                'subtitle' => '۴۰ سؤال چهارگزینه‌ای از مجموعه کریگ.',
                'description' => 'مرور ۴۰ سؤال از مجموعه کریگ.',
                'eyebrow' => 'کریگ | فصول ۶ تا ۱۰ | فصل ۱۰ - قسمت اول',
                'backHref' => '/exams/craig-dental-materials/6-10/',
                'backLabel' => 'بازگشت به فصول ۶ تا ۱۰ کریگ',
                'autoAdvance' => true,
                'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'siteSubtitle' => 'آزمون‌ها',
                'siteBadge' => 'آزمون رفرنسی',
                'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'questions' => [
                    [
                        'question' => 'در طراحی یک fixed dental prosthesis کوتاه مدت که نیروی عملکردی نسبتاً زیاد دریافت می کند، اما طول span زیاد نیست، کدام نوع alloy در طبقه بندی ANSI/ADA No. 5 با کاربرد موردنظر هماهنگ تر است؟',
                        'options' => [
                            'Type I؛ soft، برای restorations کم تنش مانند برخی inlayها',
                            'Type II؛ medium، برای inlay و onlay با تنش متوسط',
                            'Type III؛ hard، برای crowns و short-span fixed dental prostheses',
                            'Type IV؛ extra-hard، برای long-span fixed dental prostheses و removable prostheses',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن گزینه ج: Type III یا hard alloy برای restorations تحت high stress مانند crowns و short-span fixed dental prostheses معرفی شده است. رد گزینه الف: Type I برای low stress مثل برخی inlayهاست، نه fixed prosthesis با تنش زیاد. رد گزینه ب: Type II برای moderate stress مانند inlay/onlay است و برای مورد high stress مناسب تر از Type III نیست. رد گزینه د: Type IV برای very high stress مثل long-span fixed dental prostheses و removable prostheses است؛ در سوال span کوتاه است.',
                    ],
                    [
                        'question' => 'در مقایسه دو amalgam alloy با ترکیب نزدیک، یکی عمدتاً lathe-cut و دیگری عمدتاً spherical است. اگر هدف کاهش مقدار mercury لازم برای wetting باشد، کدام تبیین مناسب تر است؟',
                        'options' => [
                            'ذرات spherical سطح ویژه کمتری دارند و mercury کمتری برای wetting می خواهند.',
                            'ذرات lathe-cut نسبت Ag/Sn بالاتری دارند و mercury کمتری برای wetting می خواهند.',
                            'ذرات spherical به دلیل وجود zinc، mercury بیشتری در reaction مصرف می کنند.',
                            'ذرات lathe-cut به دلیل تشکیل η′، mercury کمتری در mix لازم دارند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: ذرات irregular/lathe-cut سطح ویژه بیشتری دارند و برای wetting به mercury بیشتری نیاز دارند؛ spherical particles معمولاً mercury کمتری می خواهند. رد گزینه ب: نسبت Ag/Sn علت اصلی تفاوت mercury requirement در این مقایسه نیست. رد گزینه ج: zinc نقش manufacturing aid دارد، نه علت مصرف بیشتر mercury در spherical particles. رد گزینه د: η′ مربوط به high-copper reaction است، نه علت نیاز کمتر mercury برای lathe-cut particles.',
                    ],
                    [
                        'question' => 'در یک سوال مفهومی درباره اصطلاحات، دانشجویی silver را در dentistry «noble metal» می نامد، چون در metallurgy گاهی چنین تلقی می شود. بر اساس متن فصل، کدام اصلاح دقیق تر است؟',
                        'options' => [
                            'silver در dentistry noble محسوب می شود، زیرا رسانایی حرارتی بالایی دارد.',
                            'silver در dentistry noble محسوب نمی شود، زیرا در oral cavity به طور قابل توجهی corrode/tarnish می شود.',
                            'silver در dentistry noble محسوب نمی شود، زیرا با gold solid solution تشکیل نمی دهد.',
                            'silver در dentistry noble محسوب می شود، اما فقط وقتی با copper همراه باشد.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: متن تاکید می کند silver در dentistry noble محسوب نمی شود، زیرا در oral cavity به طور قابل توجهی دچار corrosion/tarnish می شود و black sulfide می سازد. رد گزینه الف: رسانایی بالا دلیل noble بودن در dentistry نیست. رد گزینه ج: متن می گوید silver با gold و palladium solid solutions تشکیل می دهد. رد گزینه د: همراهی با copper silver را noble نمی کند.',
                    ],
                    [
                        'question' => 'در یک MOD restoration بزرگ، bonded amalgam نسبت به unbonded amalgam انتخاب شده است. اگر علت انتخاب فقط بر اساس متن فصل توضیح داده شود، کدام نتیجه با شواهد فصل هماهنگ تر است؟',
                        'options' => [
                            'bonded amalgam اتصال شیمیایی واقعی با dentin ایجاد می کند و tooth را کاملاً به strength طبیعی برمی گرداند.',
                            'bonded amalgam fracture resistance را بیش از unbonded amalgam افزایش می دهد، اما strength آن به intact tooth نمی رسد.',
                            'bonded amalgam عمدتاً برای افزایش amalgam-to-amalgam bond strength در repair به کار می رود.',
                            'bonded amalgam به دلیل creep صفر، نیاز به retention features را حذف می کند.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: bonded amalgam در MOD restorations fracture resistance را نسبت به unbonded amalgam بیش از دو برابر گزارش کرده، اما strength آن به intact tooth نمی رسد. رد گزینه الف: متن true adhesion بین amalgam و tooth structure را رد می کند. رد گزینه ج: bonding agents برای افزایش amalgam-to-amalgam repair bond موفق نبوده اند. رد گزینه د: bonding جایگزین کامل ملاحظات retention و creep نمی شود.',
                    ],
                    [
                        'question' => 'در microstructure یک high-copper admixed amalgam، مشاهده یک فاز Cu-Sn در اطراف ذرات spherical Ag-Cu eutectic چه اهمیتی دارد؟',
                        'options' => [
                            'نشان دهنده تشکیل γ2 و کاهش corrosion resistance است.',
                            'نشان دهنده تشکیل η′ و حذف مسیر اصلی ضعف low-copper amalgam است.',
                            'نشان دهنده افزایش Ag3Sn unreacted و کاهش strength نهایی است.',
                            'نشان دهنده تشکیل Ag2Hg3 در ذرات eutectic و کاهش matrix است.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: در high-copper amalgams تشکیل η′ یا Cu6Sn5 به جای γ2 tin-mercury phase، علت اصلی عملکرد بهتر نسبت به low-copper amalgams است. رد گزینه الف: γ2 فاز tin-mercury ضعیف و corrosion-prone در low-copper است. رد گزینه ج: unreacted particles strength را بهبود می دهند، اما سوال درباره Cu-Sn phase اطراف spherical eutectic است. رد گزینه د: Ag2Hg3 همان γ1 matrix است و توضیح اطراف Ag-Cu eutectic را نمی دهد.',
                    ],
                    [
                        'question' => 'آلیاژی با gold content برابر 18k از نظر fineness چگونه بیان می شود؟',
                        'options' => [
                            '583 fine، زیرا 18/24 معادل 58.3% gold است.',
                            '666 fine، زیرا 18k کمتر از 20k و بیشتر از 16k است.',
                            '750 fine، زیرا 18/24 معادل 75% gold است.',
                            '916 fine، زیرا carat فقط به noble-metal content اشاره دارد.',
                        ],
                        'correctIndex' => 2,
                        'explanation' => '**پاسخ درست:** گزینه ج
دلیل درست بودن گزینه ج: 18k یعنی 18/24 alloy از gold تشکیل شده است؛ بنابراین 75% gold یا 750 fine است. رد گزینه الف: 583 fine مربوط به 14k است. رد گزینه ب: 666 fine مربوط به 16k است. رد گزینه د: 916 fine مربوط به 22k است و carat/fineness فقط gold content را نشان می دهد، نه noble-metal content.',
                    ],
                    [
                        'question' => 'در creep test مربوط به dental amalgam، کدام مجموعه شرایط و تفسیر با متن فصل سازگارتر است؟',
                        'options' => [
                            'specimen یک روزه، تنش tensile برابر 36 MPa، دمای 37°C، و حد قابل قبول 0.1%',
                            'specimen هفت روزه، تنش compressive برابر 36 MPa، دمای 37°C، و حد قابل قبول 1.0%',
                            'specimen بیست وچهارساعته، تنش shear برابر 80 MPa، دمای اتاق، و حد قابل قبول 1.0%',
                            'specimen هفت روزه، تنش compressive برابر 300 MPa، دمای 37°C، و حد قابل قبول 0.45%',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: creep test برای amalgam روی cylindrical specimen هفت روزه، تحت compressive stress 36 MPa در 37°C انجام می شود و حد specification برابر 1.0% است. رد گزینه الف: specimen یک روزه، tensile stress و حد 0.1% در متن نیامده است. رد گزینه ج: shear stress و 80 MPa مربوط به creep test نیست. رد گزینه د: 300 MPa حد compressive strength پس از 24 ساعت است، نه بار creep test.',
                    ],
                    [
                        'question' => 'در یک gold-based casting alloy، افزودن palladium به مقدار حداقل حدود 10 wt% چه پیامد ظاهری ای دارد؟',
                        'options' => [
                            'alloy را white می کند.',
                            'alloy را reddish می کند.',
                            'alloy را light yellow نگه می دارد.',
                            'alloy را bluish-white و brittle می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: palladium در مقدار حدود 10 wt% یا بیشتر gold-based alloys را white می کند. رد گزینه ب: reddish color از copper حاصل می شود. رد گزینه ج: برخی Pd-In-Ag alloys می توانند yellow بمانند، اما قاعده ذکرشده برای Pd حدود 10% whitening است. رد گزینه د: bluish-white و brittle توصیف اثر palladium در gold-based alloy نیست.',
                    ],
                    [
                        'question' => 'یک بیمار پس از ترمیم تازه با spherical high-copper amalgam دچار postoperative sensitivity شده است. در صورت نبود تفاوت معنادار dimensional change با alloy دیگر، کدام سازوکار مطرح شده در فصل توضیح محتمل تری است؟',
                        'options' => [
                            'سطح مجاور cavity wall در spherical amalgam ناهموارتر است و interfacial space بیشتری برای fluid ایجاد می کند.',
                            'spherical amalgam به طور ذاتی elastic modulus پایین تری از composite دارد و pulpal fluid را پمپ می کند.',
                            'spherical amalgam به دلیل وجود γ2 بیشتر، تمام dentinal tubules را باز نگه می دارد.',
                            'spherical amalgam به علت نبود Ag2Hg3 در matrix، setting expansion شدیدی ایجاد می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن microleakage بیشتر spherical alloys را به surface texture ناهموارتر در interface و interfacial space بیشتر برای pulpal fluid مرتبط می داند. رد گزینه ب: elastic modulus amalgam در متن 40 تا 60 GPa است و دلیل sensitivity spherical alloys معرفی نشده است. رد گزینه ج: γ2 مشخصه low-copper است، نه علت اصلی sensitivity spherical high-copper. رد گزینه د: spherical high-copper matrix همچنان γ1 دارد و موضوع setting expansion شدید نیست.',
                    ],
                    [
                        'question' => 'در یک gold-based casting alloy که قابلیت ordered-solution hardening دارد، کدام توالی پردازشی با هدف «آسانی burnishing ابتدا و افزایش hardness پیش از cementation» هماهنگ تر است؟',
                        'options' => [
                            'slow cooling پس از casting، سپس quenching پیش از cementation',
                            'rapid cooling پس از casting، سپس reheating کنترل شده برای ordered structure',
                            'casting در دمای پایین تر از solidus، سپس polishing بدون heat treatment',
                            'نگهداری در acid، سپس soldering در دمای بالاتر از liquidus',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: rapid cooling alloy را در softer disordered state نگه می دارد و burnishing را آسان تر می کند؛ reheating بعدی ordered structure و hardening ایجاد می کند. رد گزینه الف: slow cooling ابتدا alloy را hard می کند و burnishing را دشوارتر می سازد. رد گزینه ج: casting زیر solidus و بدون heat treatment با سازوکار متن سازگار نیست. رد گزینه د: heating بالاتر از liquidus باعث melting/distortion می شود و acid نگهداری شده بخشی از فرایند نیست.',
                    ],
                    [
                        'question' => 'بر اساس ANSI/ADA specification No. 1 برای amalgam alloy، کدام ترکیب حداقل/حداکثر به درستی آمده است؟',
                        'options' => [
                            'compressive strength حداقل 80 MPa پس از 1 ساعت، حداقل 300 MPa پس از 24 ساعت، creep حداکثر 1%',
                            'tensile strength حداقل 80 MPa پس از 1 ساعت، compressive strength حداقل 300 MPa پس از 7 روز، creep حداکثر 0.1%',
                            'compressive strength حداقل 80 MPa پس از 24 ساعت، dimensional change بین −1 تا +1 μm/cm، creep حداکثر 1%',
                            'compressive strength حداقل 300 MPa پس از 1 ساعت، creep حداکثر 0.45%، dimensional change فقط منفی',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: specification حداقل compressive strength را 80 MPa در 1 ساعت و 300 MPa در 24 ساعت، creep حداکثر 1%، و dimensional change در بازه −15 تا +20 μm/cm می داند. رد گزینه ب: tensile strength معیار اصلی specification ذکرشده نیست و creep 0.1% نیست. رد گزینه ج: 80 MPa مربوط به 1 ساعت است نه 24 ساعت، و dimensional change بازه وسیع تری دارد. رد گزینه د: 300 MPa مربوط به 24 ساعت است نه 1 ساعت و creep مجاز 0.45% نیست.',
                    ],
                    [
                        'question' => 'یک amalgam alloy با zinc content برابر 0.02% چگونه در specification معرفی می شود و zinc در آن چه نقش اصلی ای داشته است؟',
                        'options' => [
                            'non-zinc؛ برای افزایش η′ در unicompositional alloys',
                            'zinc-containing؛ برای کمک به تولید clean, sound ingots در lathe-cut alloys',
                            'zinc-containing؛ برای کاهش solubility of silver در mercury',
                            'non-zinc؛ برای جلوگیری از trituration و working time طولانی تر',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: alloy با zinc بیش از 0.01% zinc-containing است؛ zinc برای کمک به تولید clean, sound castings ingots در lathe-cut alloy manufacturing به کار می رفته است. رد گزینه الف: 0.02% non-zinc نیست و zinc برای η′ نیست. رد گزینه ج: کاهش solubility silver در mercury نقش ذکرشده zinc نیست. رد گزینه د: 0.02% non-zinc نیست و zinc trituration را حذف نمی کند.',
                    ],
                    [
                        'question' => 'در مقایسه دو noble alloy، یکی single-phase و دیگری multiple-phase است. اگر نگرانی اصلی corrosion باشد، کدام برداشت به منبع نزدیک تر است؟',
                        'options' => [
                            'multiple-phase alloy معمولاً corrosion کمتری دارد، چون مرز فازها مانع electrochemical interaction می شود.',
                            'single-phase alloy معمولاً corrosion کمتری دارد، چون ترکیب آن همگن تر است و interaction فازی کمتری رخ می دهد.',
                            'تفاوت phase structure در corrosion نقشی ندارد و فقط density تعیین کننده است.',
                            'single-phase alloy فقط وقتی corrosion کمتری دارد که palladium نداشته باشد.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: single-phase alloy ترکیب همگن تری دارد و به دلیل کاهش interaction electrochemical بین فازها معمولاً corrosion کمتری از multiple-phase alloy دارد. رد گزینه الف: متن multiple-phase alloys را مستعد corrosion بیشتر می داند. رد گزینه ج: phase structure در corrosion نقش دارد و density تنها عامل نیست. رد گزینه د: palladium به خودی خود شرط single-phase بودن یا corrosion کمتر نیست.',
                    ],
                    [
                        'question' => 'در high-copper unicompositional amalgam، علت قرارگرفتن Cu6Sn5 به صورت ring اطراف spherical particle کدام است؟',
                        'options' => [
                            'copper به صورت جداگانه در ذرات Ag-Cu eutectic بیرون از particle قرار دارد.',
                            'همه copper در همان single particle حضور دارد و واکنش Cu/Sn پیرامون همان particle رخ می دهد.',
                            'mercury فقط copper را از matrix حل می کند و silver را دست نخورده باقی می گذارد.',
                            'zinc به عنوان nucleating center باعث ring شدن Ag2Hg3 می شود.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: در unicompositional high-copper alloy همه copper در همان spherical particle وجود دارد و واکنش copper-tin به صورت ring در اطراف particle رخ می دهد. رد گزینه الف: این توصیف مربوط به admixed alloys با Ag-Cu eutectic particles جداگانه است. رد گزینه ج: mercury عمدتاً silver و tin را حل می کند و copper solubility بسیار پایین تری دارد. رد گزینه د: zinc علت ring formation نیست.',
                    ],
                    [
                        'question' => 'در یک restoration کم تنش که clinician می خواهد marginها به راحتی burnish شوند، کدام ویژگی از type I و II alloys بیشتر علت انتخاب است؟',
                        'options' => [
                            'high elongation همراه با مناسب بودن برای low/moderate stress',
                            'high yield strength همراه با مناسب بودن برای long-span bridges',
                            'low elongation همراه با hardness بالاتر از enamel',
                            'high melting range همراه با کاربرد اختصاصی در ceramic-metal',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: Type I و II elongation بالایی دارند و برای low/moderate stress مناسب اند، بنابراین burnishing آسان تری دارند. رد گزینه ب: high yield strength و long-span بیشتر با Type IV مرتبط است. رد گزینه ج: low elongation با burnishing آسان سازگار نیست. رد گزینه د: ceramic-metal کاربرد اختصاصی Type I/II نیست.',
                    ],
                    [
                        'question' => 'در کارگاه dental، مخلوط کردن scrap سایر dental alloyها با gold مخصوص restoration چه خطری دارد؟',
                        'options' => [
                            'مقدار کمی lead یا mercury می تواند gold را brittle کند.',
                            'مقدار کمی platinum می تواند gold را در آب حل کند.',
                            'مقدار کمی silver مانع تشکیل solid solution با gold می شود.',
                            'مقدار کمی copper مانع work hardening gold می شود.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید کمتر از 0.2% lead gold را بسیار brittle می کند و mercury نیز در مقادیر کم اثر زیان بار دارد؛ بنابراین scrap alloyها نباید مخلوط شوند. رد گزینه ب: platinum gold را در آب حل نمی کند. رد گزینه ج: silver می تواند با gold solid solution تشکیل دهد. رد گزینه د: copper از عوامل hardening gold alloys است، نه مانع work hardening.',
                    ],
                    [
                        'question' => 'چون dental amalgam در tension و shear بسیار ضعیف تر از compression است، کدام طراحی cavity با منطق فصل سازگارتر است؟',
                        'options' => [
                            'طراحی ای که tensile و shear stresses را در سرویس کم و compressive stresses را بیشتر کند.',
                            'طراحی ای که تمام نیروها را به shear تبدیل کند تا brittle fracture کاهش یابد.',
                            'طراحی ای که tensile stress را افزایش دهد، چون tensile strength amalgam شبیه compressive strength است.',
                            'طراحی ای که فقط با افزایش mercury content، tensile strength را بالا ببرد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: چون amalgam در compression قوی تر و در tension/shear ضعیف تر است، cavity design باید tensile و shear stresses را کاهش دهد. رد گزینه ب: تبدیل نیروها به shear با ضعف amalgam سازگار نیست. رد گزینه ج: tensile strength amalgam فقط کسری از compressive strength است. رد گزینه د: افزایش mercury content strength را کاهش می دهد.',
                    ],
                    [
                        'question' => 'افزودن مقدار بسیار کم iridium یا ruthenium به dental casting alloys بیشتر برای کدام هدف است؟',
                        'options' => [
                            'افزایش grain size و کاهش tensile strength',
                            'grain refinement و بهبود یکنواختی و mechanical properties',
                            'کاهش noble-metal content تا زیر 25%',
                            'ایجاد oxide لازم برای porcelain bonding در همه alloys',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: مقادیر بسیار کم Ir/Ru با ایجاد nucleation centers، grain size را کاهش می دهد و tensile strength، elongation و یکنواختی properties را بهتر می کند. رد گزینه الف: هدف کاهش grain size است، نه افزایش آن. رد گزینه ج: این عناصر برای تغییر classification به base metal اضافه نمی شوند. رد گزینه د: oxide برای porcelain bonding بیشتر به عناصری مانند In/Sn/Fe/Ga مربوط است.',
                    ],
                    [
                        'question' => 'در مطالعه microleakage amalgamها، علامت گذاری spherical alloys با S چه الگوی بالینی/آزمایشگاهی ای را نشان می داد؟',
                        'options' => [
                            'spherical alloys معمولاً microleakage پایین تری از lathe-cut داشتند.',
                            'microleakage بالاتر با spherical alloys و حساسیت پس از عمل مرتبط دیده شد.',
                            'S فقط به zinc-containing بودن alloy اشاره داشت و با leakage ارتباط نداشت.',
                            'microleakage فقط در low-copper lathe-cut alloys دیده می شد.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: شکل microleakage نشان می دهد alloys علامت خورده با S، یعنی spherical alloys، با leakage بالاتر و postoperative sensitivity مرتبط بوده اند. رد گزینه الف: متن خلاف آن را گزارش می کند. رد گزینه ج: S به spherical particle alloys اشاره دارد، نه zinc-containing بودن. رد گزینه د: high leakage فقط به low-copper lathe-cut محدود نشده است.',
                    ],
                    [
                        'question' => 'کدام تعریف ترکیبی با ADA compositional classification برای casting alloys هماهنگ تر است؟',
                        'options' => [
                            'high noble: noble metal حداقل 60 wt% و gold حداقل 40 wt%',
                            'noble: noble metal کمتر از 25 wt% و gold حداقل 40 wt%',
                            'predominantly base metal: noble metal حداقل 60 wt% بدون gold',
                            'high noble: titanium حداقل 85 wt% و palladium کمتر از 10 wt%',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: high noble alloys در ADA classification حداقل 60 wt% noble metal و حداقل 40 wt% gold دارند. رد گزینه ب: noble alloys حداقل 25 wt% noble metal دارند و gold شرط ندارد. رد گزینه ج: predominantly base metal یعنی noble-metal content کمتر از 25%، نه حداقل 60%. رد گزینه د: titanium alloys در طبقه جدا با titanium content بالا مطرح اند، نه high noble.',
                    ],
                    [
                        'question' => 'در trituration amalgam alloy با liquid mercury، فاز matrix اصلی set amalgam از چه ترکیبی تشکیل می شود؟',
                        'options' => [
                            'Ag2Hg3 یا γ1',
                            'Sn7–8Hg یا γ2',
                            'Cu6Sn5 یا η′',
                            'Cu3Sn یا ε',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: mercury با silver و tin واکنش داده و Ag2Hg3 یا γ1 را به عنوان matrix اصلی set amalgam تشکیل می دهد. رد گزینه ب: γ2 همان tin-mercury phase ضعیف تر است و matrix مطلوب high-copper نیست. رد گزینه ج: η′ فاز Cu-Sn است و matrix اصلی نیست. رد گزینه د: ε یا Cu3Sn در unicompositional particles وجود دارد، نه matrix اصلی set amalgam.',
                    ],
                    [
                        'question' => 'در gold- و palladium-based alloys، copper چه نقش هایی می تواند داشته باشد؟',
                        'options' => [
                            'افزودن reddish color، solid/ordered-solution hardening، و کاهش melting point در Pd-based alloys',
                            'حذف کامل corrosion، تبدیل alloy به white، و جلوگیری از solid solution',
                            'grain refinement به دلیل melting point بسیار بالا، بدون تغییر strength',
                            'ایجاد oxide اصلی برای ceramic bonding و جلوگیری از ordered phase',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: copper در gold alloys رنگ reddish می دهد و با solid/ordered-solution hardening سختی و strength را افزایش می دهد؛ در Pd-based alloys نیز melting point را پایین آورده و strength را بالا می برد. رد گزینه ب: copper alloy را white نمی کند و corrosion را کامل حذف نمی کند. رد گزینه ج: grain refinement به Ir/Ru مربوط است. رد گزینه د: oxide اصلی ceramic bonding در این context copper نیست.',
                    ],
                    [
                        'question' => 'جایگزینی low-copper amalgam با high-copper amalgam در dentistry مدرن بیشتر به کدام مجموعه پیامد نسبت داده شده است؟',
                        'options' => [
                            'افزایش strength، corrosion resistance، marginal integrity و clinical performance',
                            'کاهش Ag3Sn، افزایش γ2، و marginal breakdown بیشتر',
                            'افزایش zinc، کاهش copper به زیر 5%، و service life کوتاه تر',
                            'کاهش compressive strength اولیه و افزایش creep بالای 1%',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: high-copper formulations به دلیل strength، corrosion resistance، marginal integrity و clinical performance بهتر جایگزین low-copper شدند. رد گزینه ب: افزایش γ2 و marginal breakdown بیشتر ویژگی low-copper است. رد گزینه ج: copper در high-copper به 13 تا 30% می رسد، نه زیر 5%. رد گزینه د: high-copper creep پایین تر و early strength بهتر دارد.',
                    ],
                    [
                        'question' => 'platinum در gold-based dental alloys بیشتر کدام اثر را نشان می دهد؟',
                        'options' => [
                            'افزایش hardness و elastic qualities و روشن تر کردن رنگ yellow gold-based alloys',
                            'کاهش melting range و تبدیل alloy به reddish color',
                            'ایجاد rapid corrosion در oral conditions و کاهش fusing point',
                            'جایگزینی کامل gold و تبدیل alloy به non-noble base metal',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: platinum hardness و elastic qualities gold را افزایش می دهد و رنگ yellow gold-based alloys را روشن تر می کند. رد گزینه ب: copper عامل reddish color است، نه platinum. رد گزینه ج: platinum به دلیل high fusing point و resistance کاربرد دارد، نه rapid corrosion. رد گزینه د: platinum alloy را به base metal تبدیل نمی کند.',
                    ],
                    [
                        'question' => 'اگر فازهای خالص amalgam از نظر corrosion resistance مقایسه شوند، کدام توالی با متن فصل نزدیک تر است؟',
                        'options' => [
                            'γ1 مقاوم ترین است و γ2 در انتهای ضعیف تر توالی قرار می گیرد.',
                            'γ2 مقاوم ترین است و γ1 در انتهای ضعیف تر توالی قرار می گیرد.',
                            'Cu6Sn5 از γ1 مقاوم تر است و Ag3Sn کمترین مقاومت را دارد.',
                            'همه فازها corrosion potential یکسان دارند و فقط saliva تعیین کننده است.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در متن، γ1 بیشترین corrosion resistance را دارد و γ2 یا Sn7–8Hg در انتهای کم مقاومت تر توالی قرار می گیرد. رد گزینه ب: دقیقاً خلاف توالی متن است. رد گزینه ج: Cu6Sn5 از γ1 مقاوم تر نیست. رد گزینه د: فازهای amalgam corrosion potentials متفاوت دارند.',
                    ],
                    [
                        'question' => 'در casting یک alloy، چرا density در کامل شدن casting اهمیت دارد؟',
                        'options' => [
                            'alloyهای با density بیشتر معمولاً در mold سریع تر شتاب می گیرند و casting کامل تر را آسان تر می کنند.',
                            'alloyهای با density کمتر همیشه casting کامل تر می دهند، چون surface tension بیشتری دارند.',
                            'density فقط color alloy را تعیین می کند و به mold filling ارتباطی ندارد.',
                            'density بالا باعث کاهش melting range تا زیر دمای soldering می شود.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: density بالاتر باعث acceleration بهتر molten alloy در mold و castings کامل تر می شود. رد گزینه ب: متن lower-density alloys را دشوارتر برای cast می داند. رد گزینه ج: density به casting process مرتبط است. رد گزینه د: density مستقیماً melting range را تا زیر soldering temperature کاهش نمی دهد.',
                    ],
                    [
                        'question' => 'در set mass یک dental amalgam، باقی ماندن بخشی از alloy particles به صورت unreacted چه اثری دارد؟',
                        'options' => [
                            'strength final material را افزایش می دهد.',
                            'γ2 را به matrix اصلی تبدیل می کند.',
                            'working time را پس از hardening طولانی می کند.',
                            'dimensional change را همیشه مثبت می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: چون mercury برای واکنش کامل کافی نیست، حدود بخشی از particles unreacted باقی می مانند و strength final amalgam را افزایش می دهند. رد گزینه ب: matrix اصلی γ1 است، نه γ2. رد گزینه ج: پس از hardening working time ادامه ندارد. رد گزینه د: unreacted particles dimensional change را همیشه مثبت نمی کند.',
                    ],
                    [
                        'question' => 'در ارزیابی hardness یک noble dental casting alloy، کدام پیامد کلینیکی با منبع هماهنگ است؟',
                        'options' => [
                            'اگر hardness alloy از enamel بیشتر باشد، می تواند enamel مقابل restoration را wear کند.',
                            'اگر hardness alloy از enamel کمتر باشد، finishing و polishing غیرممکن می شود.',
                            'hardness فقط density را تغییر می دهد و در wear نقشی ندارد.',
                            'noble alloys همیشه hardness بالاتر از enamel دارند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: اگر hardness alloy از enamel بیشتر باشد، احتمال wear enamel مقابل restoration وجود دارد. رد گزینه ب: hardness پایین تر از enamel finishing را غیرممکن نمی کند. رد گزینه ج: hardness در cutting/finishing/polishing و wear مهم است. رد گزینه د: noble alloys معمولاً hardness کمتر از base-metal alloys دارند و لزوماً بالاتر از enamel نیستند.',
                    ],
                    [
                        'question' => 'در bonded amalgam، bond ثبت شده در shear bond tests از نظر ماهیت چگونه توضیح داده می شود؟',
                        'options' => [
                            'true chemical adhesion مستقیم بین mercury و dentin',
                            'commingling bonding agent و amalgam در common interface',
                            'crystallization مستقیم Ag3Sn در collagen dentin',
                            'اتصال مکانیکی صرفاً با pins بدون نقش bonding agent',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: متن می گوید true adhesion وجود ندارد و bond در tests ناشی از commingling bonding agent و amalgam در common interface است. رد گزینه الف: chemical adhesion مستقیم mercury-dentin بیان نشده و رد شده است. رد گزینه ج: Ag3Sn در collagen dentin crystallize نمی شود. رد گزینه د: pins retention features هستند، اما bonded amalgam از bonding agent نیز بهره می برد.',
                    ],
                    [
                        'question' => 'کدام طبقه از typical noble casting alloys «بدون gold اما با palladium کافی» هنوز در ADA classification در گروه noble قرار می گیرد؟',
                        'options' => [
                            'Ag-Pd alloys',
                            'Au-Ag-Pt alloys',
                            'Au-Cu-Ag-Pd-I alloys',
                            'Au-Cu-Ag-Pd-II alloys',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: Ag-Pd alloys gold ندارند اما به دلیل palladium content در ADA specification جزو noble alloys محسوب می شوند. رد گزینه ب: Au-Ag-Pt high noble و gold-containing است. رد گزینه ج: Au-Cu-Ag-Pd-I gold بالایی دارد و high noble است. رد گزینه د: Au-Cu-Ag-Pd-II نیز gold-containing high noble است.',
                    ],
                    [
                        'question' => 'در مقایسه wt% و at% در ترکیب casting alloys، کدام گزاره دقیق تر است؟',
                        'options' => [
                            'wt% بیشتر در manufacturing و sales به کار می رود، اما properties از نظر at% بهتر فهمیده می شوند.',
                            'at% فقط برای color کاربرد دارد و wt% فقط برای corrosion.',
                            'wt% و at% برای همه عناصر یکسان اند، چون dental alloys هم جرم اند.',
                            'at% برای noble alloys ممنوع است و فقط در base metals کاربرد دارد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن بیان می کند wt% در manufacturing/sales رایج تر است، اما physical, chemical و biological properties از نظر atomic percentage بهتر فهمیده می شوند. رد گزینه ب: کاربردها به color و corrosion محدود نیستند. رد گزینه ج: wt% و at% به علت جرم اتمی متفاوت عناصر یکسان نیستند. رد گزینه د: at% در noble alloys نیز مطرح است.',
                    ],
                    [
                        'question' => 'کدام جفت عنصر-نقش در dental alloys با متن فصل سازگارتر است؟',
                        'options' => [
                            'zinc: deoxidizer؛ gallium: کمک به bonding ceramic از طریق oxides در برخی alloys',
                            'tin: grain refiner؛ iridium: کاهش surface tension در casting',
                            'nickel: حذف آلرژی؛ indium: افزایش black sulfide در mouth',
                            'silver: عامل اصلی passivation؛ copper: جلوگیری از hardening',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: zinc در dental alloys deoxidizer است و gallium oxides در bonding ceramic به metal در برخی ceramic alloys اهمیت دارند. رد گزینه ب: tin grain refiner نیست؛ Ir/Ru grain refiners هستند. رد گزینه ج: nickel allergen شناخته می شود و indium black sulfide ایجاد نمی کند. رد گزینه د: passivation عمدتاً در stainless steel به chromium oxide مربوط است؛ copper باعث hardening می شود.',
                    ],
                    [
                        'question' => 'برتری high-copper amalgam نسبت به low-copper amalgam عمدتاً به کدام تغییر فازی مرتبط است؟',
                        'options' => [
                            'تشکیل tin-copper compound به جای tin-mercury compound ضعیف و corrosion-prone',
                            'حذف Ag2Hg3 از matrix و جایگزینی آن با pure mercury',
                            'افزایش Sn7–8Hg به عنوان تنها فاز matrix',
                            'تبدیل همه Ag3Sn به Cu3Sn پیش از trituration',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: high-copper amalgam با تشکیل Cu6Sn5 یا η′ از تشکیل tin-mercury γ2 ضعیف و corrosion-prone جلوگیری می کند. رد گزینه ب: Ag2Hg3 یا γ1 همچنان matrix اصلی است. رد گزینه ج: افزایش γ2 هدف low-copper است نه high-copper. رد گزینه د: همه Ag3Sn به Cu3Sn پیش از trituration تبدیل نمی شود.',
                    ],
                    [
                        'question' => 'در میان amalgamهای جدول خواص، کدام گروه معمولاً early compressive strength بالاتری نشان می دهد؟',
                        'options' => [
                            'low-copper lathe-cut alloys',
                            'high-copper unicompositional alloys',
                            'low-copper spherical alloys',
                            'admixed regular alloys بدون copper',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: high-copper unicompositional alloys در جدول early compressive strength بالاتری، بیش از 250 MPa، نشان داده اند. رد گزینه الف: low-copper lathe-cut early strength بسیار پایین تر است. رد گزینه ج: low-copper spherical به اندازه high-copper unicompositional early strength ندارد. رد گزینه د: admixed regular بدون copper با high-copper unicompositional قابل قیاس نیست.',
                    ],
                    [
                        'question' => 'چرا pure silver برای dental restorations مناسب نیست؟',
                        'options' => [
                            'در mouth black sulfide تشکیل می دهد.',
                            'در clean dry air به سرعت می سوزد.',
                            'با palladium هیچ solid solution نمی سازد.',
                            'melting point آن از همه noble metals بالاتر است.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: pure silver در mouth black sulfide تشکیل می دهد و به همین دلیل در dental restorations به صورت pure استفاده نمی شود. رد گزینه ب: silver در clean dry air unaltered است. رد گزینه ج: silver با palladium solid solution تشکیل می دهد. رد گزینه د: melting point silver از بسیاری noble metals پایین تر است.',
                    ],
                    [
                        'question' => 'working time در amalgam از کدام لحظه تا کدام وضعیت تعریف می شود؟',
                        'options' => [
                            'از پایان trituration تا زمانی که amalgam hard و nonworkable شود.',
                            'از شروع carving تا پایان 7-day strength gain.',
                            'از آغاز condensation تا complete corrosion products formation.',
                            'از شروع contact با saliva تا تشکیل protein pellicle.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: working time فاصله پایان trituration تا زمانی است که amalgam hard شده و دیگر condensable/carvable نباشد. رد گزینه ب: 7-day strength gain completion reaction را نشان می دهد، نه working time. رد گزینه ج: corrosion product formation تعریف working time نیست. رد گزینه د: protein pellicle مربوط به corrosion protection است، نه working time.',
                    ],
                    [
                        'question' => 'کدام مجموعه، «مزیت های اصلی» dental amalgam را بهتر بازنمایی می کند؟',
                        'options' => [
                            'insertion نسبتاً آسان، technique sensitivity پایین، resistance مناسب به fracture و service life نسبتاً طولانی',
                            'color matching کامل، bonding ذاتی به tooth structure، و reinforcement کامل weakened tooth',
                            'نبود corrosion، نبود galvanic action، و حذف regulatory concerns',
                            'نیاز به ابزار پیچیده laboratory و کاربرد عمدتاً anterior esthetic veneers',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن amalgam را easy to insert، not overly technique sensitive، با resistance مناسب به fracture و relatively long service life توصیف می کند. رد گزینه ب: silver color با tooth match ندارد و amalgam tooth را reinforce نمی کند. رد گزینه ج: corrosion، galvanic action و regulatory concerns جزو معایب اند. رد گزینه د: amalgam direct posterior/core material است، نه anterior esthetic veneer laboratory material.',
                    ],
                    [
                        'question' => 'اصطلاح precious metals در متن فصل چرا با noble metals مترادف کامل نیست؟',
                        'options' => [
                            'precious به هزینه و معامله در commodities market اشاره دارد، اما noble به رفتار corrosion/oxidation مربوط است.',
                            'precious فقط برای titanium به کار می رود، اما noble فقط برای stainless steel.',
                            'precious یعنی metal حتماً در mouth corrode می شود، اما noble یعنی حتماً سفید است.',
                            'precious و noble هر دو فقط به gold content و carat اشاره دارند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: precious به cost و commodities market اشاره دارد، ولی noble به مقاومت در oxidation/tarnish/corrosion مربوط است؛ silver نمونه ای است که precious هست اما در dentistry noble نیست. رد گزینه ب: titanium و stainless steel در این اصطلاحات قرار نمی گیرند. رد گزینه ج: precious به corrosion الزامی یا رنگ white مربوط نیست. رد گزینه د: carat/fineness فقط gold content را بیان می کند.',
                    ],
                    [
                        'question' => 'در alloyی که ordered solution hardening نشان می دهد، slow cooling پس از casting چه اثری دارد؟',
                        'options' => [
                            'تشکیل ordered crystals و افزایش hardness/strength',
                            'حفظ کامل disordered soft state و افزایش burnishability',
                            'حذف تمام elongation بدون افزایش hardness',
                            'تشکیل porosity به دلیل zinc oxide lag',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در alloy مناسب، slow cooling فرصت ordering اتمی می دهد و ordered crystals باعث افزایش hardness و strength می شوند. رد گزینه ب: rapid cooling disordered soft state را حفظ می کند. رد گزینه ج: ordering elongation را کاهش می دهد، اما همزمان strength/hardness را افزایش می دهد. رد گزینه د: zinc oxide lag مربوط به deoxidizer در casting است، نه ordered solution hardening.',
                    ],
                    [
                        'question' => 'در admixed regular amalgam alloy، ترکیب مورفولوژیک معمولاً چگونه توصیف می شود؟',
                        'options' => [
                            '33% تا 60% spherical Ag-Cu eutectic particles و باقی irregular particles',
                            '100% spherical Ag3Sn بدون copper و zinc',
                            '100% lathe-cut Cu3Sn particles بدون silver',
                            '70% to 90% irregular pure mercury particles و باقی glass fillers نیمه دوم فصل 10',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: admixed regular alloy معمولاً شامل 33% تا 60% spherical particles نزدیک Ag-Cu eutectic و بقیه irregular particles است. رد گزینه ب: unicompositional spherical 100% Ag3Sn بدون copper نیست. رد گزینه ج: lathe-cut pure Cu3Sn بدون silver توصیف amalgam alloy نیست. رد گزینه د: mercury particle در alloy powder وجود ندارد و glass filler مربوط به composite است. نیمه دوم فصل 10',
                    ],
                ],
            ],
            [
                'slug' => '10-2',
                'path' => '/exams/craig-dental-materials/6-10/10-2/',
                'questionCount' => 40,
                'label' => 'فصل ۱۰ - قسمت دوم',
                'title' => 'آزمون فصل ۱۰ - قسمت دوم',
                'subtitle' => '۴۰ سؤال چهارگزینه‌ای از مجموعه کریگ.',
                'description' => 'مرور ۴۰ سؤال از مجموعه کریگ.',
                'eyebrow' => 'کریگ | فصول ۶ تا ۱۰ | فصل ۱۰ - قسمت دوم',
                'backHref' => '/exams/craig-dental-materials/6-10/',
                'backLabel' => 'بازگشت به فصول ۶ تا ۱۰ کریگ',
                'autoAdvance' => true,
                'siteTitle' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'siteSubtitle' => 'آزمون‌ها',
                'siteBadge' => 'آزمون رفرنسی',
                'footerText' => 'ورودی ۱۴۰۲ دندانپزشکی تهران',
                'questions' => [
                    [
                        'question' => 'در ceramic-metal restoration، اگر coefficient of thermal expansion فلز کمی بالاتر از ceramic باشد، پیامد مطلوب هنگام cooling چیست؟',
                        'options' => [
                            'ceramic در slight compression قرار می گیرد و مقاومت به crack propagation بهتر می شود.',
                            'metal در tension شدید قرار می گیرد و ceramic از bonding جدا می شود.',
                            'ceramic در shear خالص قرار می گیرد و firing temperature کاهش می یابد.',
                            'metal و ceramic هر دو melt می شوند و sag resistance افزایش می یابد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: وقتی CTE فلز کمی بالاتر باشد، پس از cooling ceramic در compression مختصر قرار می گیرد و crack propagation resistance بهتر می شود. رد گزینه ب: هدف جدایش ceramic از metal نیست. رد گزینه ج: متن shear خالص را مکانیسم مطلوب معرفی نمی کند. رد گزینه د: melting هر دو جزء در cooling رخ نمی دهد و sag resistance موضوع جداگانه ای است.',
                    ],
                    [
                        'question' => 'laboratory technician هنگام grinding یک Be-containing base-metal alloy در معرض نگرانی بهداشتی است. بر اساس متن، چرا استفاده از این alloys باید محدود یا با کنترل انجام شود؟',
                        'options' => [
                            'beryllium castability را بدتر می کند و هیچ منفعتی برای bond ندارد.',
                            'beryllium در vapor/particulate form با contact dermatitis و بیماری های ریوی و دیگر خطرها مرتبط است.',
                            'beryllium فقط باعث allergy به nickel می شود و به ventilation ارتباط ندارد.',
                            'beryllium در ISO بدون محدودیت پذیرفته شده است.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: beryllium هرچند castability و porcelain-metal bond را بهبود می دهد، در vapor/particulate form با contact dermatitis، chronic lung disease، lung carcinoma و osteosarcoma مرتبط است. رد گزینه الف: متن castability را با Be بهتر می داند، نه بدتر. رد گزینه ج: Be فقط مسیله nickel allergy نیست. رد گزینه د: ISO beryllium content را به 0.02 wt% محدود می کند.',
                    ],
                    [
                        'question' => 'علت اصلی corrosion resistance و biocompatibility مطلوب titanium در dentistry کدام است؟',
                        'options' => [
                            'تشکیل oxide layer بسیار پایدار و repassivation بسیار سریع',
                            'نبود هرگونه واکنش با oxygen در دمای بالا',
                            'density بسیار بالا و elastic modulus بالاتر از Co-Cr',
                            'تشکیل chromium carbide در grain boundaries',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: titanium oxide layer پایدار با ضخامت angstrom-level تشکیل می دهد و در nanoseconds repassivate می شود؛ این پایه corrosion resistance و biocompatibility آن است. رد گزینه ب: titanium در دمای بالا با gases مانند oxygen واکنش پذیر است. رد گزینه ج: titanium density پایین تر و modulus پایین تری از بسیاری base metals دارد. رد گزینه د: chromium carbide پدیده stainless steel/base alloy است، نه titanium biocompatibility.',
                    ],
                    [
                        'question' => 'یک wrought wire طی soldering طولانی بیش از حد گرم شده و خواص مکانیکی خود را از دست داده است. کدام تغییر microstructural محتمل تر است؟',
                        'options' => [
                            'تبدیل fibrous structure ناشی از cold work به grained structure شبیه cast form',
                            'تبدیل α-titanium به hydroxyapatite coating',
                            'تشکیل Ag2Hg3 matrix درون wire',
                            'کاهش grain size به دلیل iridium nucleation',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: heating excessive در wrought forms باعث recrystallization و تبدیل fibrous microstructure حاصل از cold work به grained cast-like structure می شود و properties افت می کند. رد گزینه ب: hydroxyapatite coating موضوع implant surface است. رد گزینه ج: Ag2Hg3 matrix مربوط به amalgam است. رد گزینه د: Ir nucleation مربوط به grain refinement در casting alloys است.',
                    ],
                    [
                        'question' => 'در انتخاب Au-Pd alloy برای ceramic-metal restoration، کدام توصیف دقیق تر است؟',
                        'options' => [
                            'white alloy با gold کاهش یافته و palladium بالا؛ stronger/stiffer/harder از Au-Pt-Pd، اما چالش esthetic بیشتر',
                            'yellow alloy با platinum بالا؛ low sag resistance کمتر از همه و بدون indium',
                            'alloy بدون noble metal؛ عمدتاً با chromium passivation مقاوم می شود',
                            'alloy حاوی silver زیاد؛ عامل اصلی greening در همه ceramics',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: Au-Pd alloys white هستند، gold کمتر و palladium بالاتر دارند، از Au-Pt-Pd stronger/stiffer/harderند و به دلیل رنگ white تولید esthetics را دشوارتر می کنند. رد گزینه ب: این توصیف بیشتر به Au-Pt-Pd و نه Au-Pd نزدیک است. رد گزینه ج: Au-Pd noble/high-noble است و chromium passivation ندارد. رد گزینه د: silver بالا و greening بیشتر درباره Pd-Ag context مطرح است.',
                    ],
                    [
                        'question' => 'طبق ANSI/ADA specification No. 14 برای base-metal alloys در removable dental prostheses، کدام شرط ترکیبی/مکانیکی درست است؟',
                        'options' => [
                            'chromium کمتر از 20% و total Cr+Co+Ni کمتر از 60% مجاز است.',
                            'chromium حداقل 20% و total Cr+Co+Ni حداقل 85%، همراه با حداقل هایی برای elongation، yield strength و elastic modulus لازم است.',
                            'gold حداقل 40% و noble content حداقل 60% باید وجود داشته باشد.',
                            'titanium حداقل 85% و beryllium حداقل 2% برای framework ضروری است.',
                        ],
                        'correctIndex' => 1,
                        'explanation' => '**پاسخ درست:** گزینه ب
دلیل درست بودن گزینه ب: specification No.14 chromium حداقل 20%، مجموع Cr+Co+Ni حداقل 85%، و حداقل elongation 1.5%، yield strength 500 MPa و elastic modulus 170 GPa را مطرح می کند. رد گزینه الف: chromium کمتر از 20% و total کمتر از 85% با specification سازگار نیست. رد گزینه ج: gold/noble content مربوط به classification casting alloys است، نه base-metal No.14. رد گزینه د: titanium و beryllium شرط ضروری framework طبق این specification نیستند.',
                    ],
                    [
                        'question' => 'در Ni-Ti orthodontic alloy، shape-memory effect با کدام توالی رخ می دهد؟',
                        'options' => [
                            'deforming below TTR، سپس heating above TTR و transformation از martensitic به austenitic',
                            'deforming above TTR، سپس cooling below TTR و تشکیل chromium oxide',
                            'melting در 883°C، سپس casting در vacuum و تشکیل equiaxed α grains',
                            'soldering در 650°C، سپس work hardening و sensitization',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در Ni-Ti، below TTR alloy plastically deformed می شود و با heating above TTR transformation martensitic به austenitic رخ می دهد و شکل اولیه بازمی گردد. رد گزینه ب: chromium oxide به Ni-Ti shape-memory مربوط نیست. رد گزینه ج: melting/casting titanium و equiaxed grains موضوع Ti-6Al-4V است. رد گزینه د: sensitization مربوط به stainless steel است.',
                    ],
                    [
                        'question' => 'وجود IdentAlloy certificate در پرونده بیمار بیشتر چه ارزش عملی ای دارد؟',
                        'options' => [
                            'نشان دادن complete composition، manufacturer/name و ADA classification برای پیگیری مشکلاتی مانند allergy یا برنامه ریزی restorations بعدی',
                            'تضمین اینکه alloy حتماً high noble است و هیچ corrosion ندارد',
                            'جایگزین کردن تمام آزمون های biocompatibility انسانی و حیوانی',
                            'تعیین shade ceramic بدون نیاز به اطلاعات alloy',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: IdentAlloy certificate ترکیب کامل، manufacturer/name و ADA classification را در اختیار dentist/patient می گذارد و در allergy، restoration contact و modificationهای بعدی مفید است. رد گزینه ب: certificate تضمین high noble بودن همه alloys یا عدم corrosion نیست. رد گزینه ج: جایگزین مستقیم آزمون biocompatibility نیست. رد گزینه د: shade ceramic هدف اصلی certificate نیست.',
                    ],
                    [
                        'question' => 'در Co-Cr alloy مخصوص removable prosthesis، کدام microstructure با low elongation و clean surface پس از casting مرتبط دانسته شده است؟',
                        'options' => [
                            'carbides continuous along grain boundaries',
                            'spherical discontinuous island carbides پس از heating بیش از 100°C بالاتر از melting temperature',
                            'equiaxed α grains با aspect ratio نزدیک unity',
                            'fibrous cold-worked microstructure بدون carbide',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: continuous carbides along grain boundaries در Co-Cr alloy پس از casting سریع، low elongation و clean surface می دهد. رد گزینه ب: islandlike spherical carbides پس از overheated casting elongation خوب اما poor surface می دهند. رد گزینه ج: equiaxed α grains مربوط به Ti-6Al-4V implants است. رد گزینه د: fibrous cold-worked microstructure مربوط به wrought alloys است.',
                    ],
                    [
                        'question' => 'مقایسه Co-Cr و Ni-Cr alloys برای ceramic-metal restorations کدام گزاره را تایید می کند؟',
                        'options' => [
                            'Co-Cr alloys معمولاً stronger/harder از noble و Ni-Cr هستند، اما casting و soldering آن ها دشوارتر است.',
                            'Ni-Cr alloys همیشه yield strength بالاتری از Co-Cr دارند و elastic modulus پایین تری از resin composite دارند.',
                            'Co-Cr alloys به جای chromium با zinc passivation پیدا می کنند.',
                            'Ni-Cr alloys فقط برای removable frameworks و نه ceramic-metal restorations به کار می روند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: Co-Cr alloys for ceramic-metal restorations stronger/harder از noble و Ni-Cr معرفی شده اند، اما casting/soldering و accuracy دشوارتر است. رد گزینه ب: Ni-Cr همیشه yield strength بالاتر ندارد و elastic modulus آن از resin composite بالاتر است. رد گزینه ج: passivation این alloys از chromium است، نه zinc. رد گزینه د: Ni-Cr alloys برای ceramic-metal restorations کاربرد دارند.',
                    ],
                    [
                        'question' => 'beta-titanium orthodontic wire نسبت به stainless steel wires چه مزیت عملکردی مطرح شده ای دارد؟',
                        'options' => [
                            'lower force magnitudes، lower elastic modulus، higher springback و larger working range',
                            'higher density، higher force magnitudes، brittle behavior و نبود weldability',
                            'shape-memory وابسته به martensite/austenite همانند Nitinol industrial alloy',
                            'نیاز به casting در 1760°C پیش از هر activation',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: beta-titanium نسبت به stainless steel lower force magnitudes، lower elastic modulus، higher springback، good ductility/weldability/corrosion resistance و larger working range دارد. رد گزینه ب: اینها مزیت beta-titanium نیستند. رد گزینه ج: shape-memory martensite/austenite ویژگی Ni-Ti است. رد گزینه د: beta-titanium wrought wire است و فعال سازی آن نیازمند casting 1760°C نیست.',
                    ],
                    [
                        'question' => 'در cast titanium، کدام مجموعه عامل ها مهم ترین منشا دشواری processing است؟',
                        'options' => [
                            'high melting point، high chemical reactivity، low casting efficiency، porosity و finishing difficulty',
                            'low melting point، high density، نبود oxide layer و excessive ductility',
                            'high gold content، low corrosion resistance و carat/fineness نامعلوم',
                            'آسانی soldering، عدم واکنش با gases و نبود نیاز به vacuum/inert atmosphere',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن دشواری cast titanium را به high melting point/reactivity، low casting efficiency، inadequate investment expansion، porosity و finishing difficulty نسبت می دهد. رد گزینه ب: titanium high melting point و high oxygen affinity دارد، نه low melting point و no oxide layer. رد گزینه ج: gold content در titanium مطرح نیست. رد گزینه د: titanium welding/soldering/machining/finishing/adjusting دشوارتر است و vacuum/inert atmosphere مهم است.',
                    ],
                    [
                        'question' => 'بیمار سابقه allergy به nickel را گزارش می کند و removable prosthesis با تماس soft tissue نیاز دارد. کدام تصمیم با متن فصل هماهنگ تر است؟',
                        'options' => [
                            'استفاده از cobalt-chromium alloy بدون nickel یا nonnickel-containing alloy',
                            'استفاده ترجیحی از nickel-chromium alloy با finishing خشک داخل دهان',
                            'افزودن beryllium برای کاهش allergenicity nickel',
                            'انتخاب stainless steel 18–8، چون nickel در آن وجود ندارد',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: برای بیمار با history of allergic response to nickel، متن استفاده از cobalt-chromium alloy بدون nickel یا nonnickel-containing alloy را پیشنهاد می کند. رد گزینه ب: Ni-Cr و finishing خشک exposure را افزایش می دهد. رد گزینه ج: Be allergenicity nickel را حذف نمی کند. رد گزینه د: 18–8 stainless steel حدود 8% nickel دارد.',
                    ],
                    [
                        'question' => 'Pd-Cu alloys برای ceramic-metal restorations با کدام پروفایل بهتر شناخته می شوند؟',
                        'options' => [
                            'palladium بالا با 10% تا 15% copper، high strength/hardness، low sag resistance و dark oxides',
                            'gold بالا با platinum 10%، رنگ yellow و lowest hardness',
                            'silver بالا بدون palladium، greening واضح و density شبیه Au-Pt-Pd',
                            'chromium بالا با carbide strengthening و کاربرد اصلی در RPD frameworks',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: Pd-Cu alloys حاوی palladium بالا و 10% تا 15% copper هستند، high strength/hardness دارند، اما low sag resistance و dark oxides نیز دارند. رد گزینه ب: Au-Pt-Pd gold/platinum بالا و yellow است، نه Pd-Cu. رد گزینه ج: silver بالا و greening مربوط به Pd-Ag context است. رد گزینه د: chromium-rich carbide-strengthened توصیف base-metal framework alloys است.',
                    ],
                    [
                        'question' => 'direct metal laser sintering در ساخت Co-Cr restorations چگونه عمل می کند؟',
                        'options' => [
                            'high-power laser لایه های پودر فلز با ضخامت حدود 0.02 mm را successive fuse می کند.',
                            'molten mercury با Ag3Sn واکنش می دهد و framework را در چند ساعت hard می کند.',
                            'electric arc titanium را در copper crucible melt کرده و با centrifugal casting وارد mold می کند.',
                            'ceramic را در دمای پایین روی wrought wire cold-work می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: direct metal laser sintering با high-power laser successive 0.02-mm-thick layers of powdered metal را fuse می کند. رد گزینه ب: این واکنش amalgamation است. رد گزینه ج: این توصیف casting titanium با electric arc/centrifugal است. رد گزینه د: cold work و ceramic firing توصیف DMLS نیستند.',
                    ],
                    [
                        'question' => '«18–8 stainless steel» در dentistry به کدام ترکیب نزدیک است؟',
                        'options' => [
                            'حدود 18% chromium و 8% nickel، با balance عمدتاً iron',
                            'حدود 18% nickel و 8% chromium، با balance عمدتاً titanium',
                            '18% gold و 8% palladium، با balance silver',
                            '18% molybdenum و 8% carbon، با balance chromium',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: 18–8 stainless steel تقریباً 18% chromium و 8% nickel دارد و balance آن عمدتاً iron است. رد گزینه ب: نسبت Cr/Ni برعکس شده و balance titanium نیست. رد گزینه ج: gold/palladium ترکیب stainless steel نیست. رد گزینه د: molybdenum/carbon به این نسبت ترکیب 18–8 نیستند.',
                    ],
                    [
                        'question' => 'در Ti-6Al-4V surgical implants، کدام microstructure از نظر fatigue crack initiation مناسب تر معرفی شده است؟',
                        'options' => [
                            'fine equiaxed α grains، β well-dispersed و small α/β interface area',
                            'coarse lamellar colonies با α/β surface area بیشتر',
                            'continuous chromium carbides در grain boundaries',
                            'oxygen-enriched surface layer تا 100 μm',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: microstructures با α-grain کمتر از 20 μm، β well-dispersed و α/β interface area کم، fatigue crack initiation را بهتر resist کرده و high-cycle fatigue strength بهتری دارند. رد گزینه ب: lamellar microstructures fatigue strength پایین تری دارند. رد گزینه ج: chromium carbides مربوط به stainless/base-metal alloys است. رد گزینه د: oxygen-enriched surface layer embrittling است و strength/ductility را کاهش می دهد.',
                    ],
                    [
                        'question' => 'کدام گزینه یکی از requirements عمومی base-metal substitutes برای noble alloys نیست؟',
                        'options' => [
                            'chemical nature نباید toxicologic/allergic effect ایجاد کند.',
                            'prosthesis باید در oral fluids corrosion و physical change را تحمل کند.',
                            'fabrication باید برای average dentist و skilled technician feasible باشد.',
                            'alloy باید لزوماً حداقل 40 wt% gold داشته باشد.',
                        ],
                        'correctIndex' => 3,
                        'explanation' => '**پاسخ درست:** گزینه د
دلیل درست بودن گزینه د: داشتن حداقل 40 wt% gold شرط high noble alloys است، نه requirement عمومی base-metal substitute. رد گزینه الف: متن نبود toxicologic/allergic effect را requirement می داند. رد گزینه ب: resistance to corrosion/physical changes در oral fluids requirement است. رد گزینه ج: feasible بودن fabrication برای dentist/technician requirement است.',
                    ],
                    [
                        'question' => 'Au-Pt-Pd alloys در ceramic-metal restorations معمولاً کدام ویژگی را دارند؟',
                        'options' => [
                            'noble-metal content بسیار بالا، yellow color، oxides از In/Sn/Fe برای bonding و cost بالا',
                            'بدون gold و با silver بالا، lowest noble-metal content و greening شایع',
                            'chromium-rich، carbide-strengthened و نیازمند passivation',
                            'nickel-rich، allergy-free و low casting temperature',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: Au-Pt-Pd alloys noble content بسیار بالا، yellow color، In/Sn/Fe برای oxide bond، Re به عنوان grain refiner و cost بالا دارند. رد گزینه ب: این توصیف به Pd-Ag alloys نزدیک تر است. رد گزینه ج: chromium-rich carbide-strengthened مربوط به base-metal alloys است. رد گزینه د: nickel-rich و allergy-free درباره Au-Pt-Pd درست نیست.',
                    ],
                    [
                        'question' => 'در removable dental prosthesis clasps، چرا yield strength و fatigue resistance اهمیت ویژه دارند؟',
                        'options' => [
                            'claspها هنگام insertion/removal دچار strain می شوند؛ permanent deformation و fatigue fracture باید کنترل شود.',
                            'claspها فقط در compression static قرار دارند و هرگز cyclic loading ندارند.',
                            'yield strength فقط shade alloy را تعیین می کند و fatigue فقط در ceramics مطرح است.',
                            'چون alloyهای RPD باید elastic modulus پایین تر از acrylic resin داشته باشند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: claspهای removable prostheses هنگام insertion/removal مرتباً strain می شوند؛ yield strength از permanent deformation و fatigue resistance از fracture چرخه ای محافظت می کند. رد گزینه ب: claspها cyclic strain دارند. رد گزینه ج: yield strength و fatigue خواص مکانیکی اند، نه shade. رد گزینه د: هدف RPD alloys لزوماً modulus پایین تر از acrylic نیست؛ rigid prosthesis معمولاً مطلوب است.',
                    ],
                    [
                        'question' => 'اگر chromium content در austenitic stainless steel کمتر از حدود 13% باشد، چه پیامدی طبق متن محتمل است؟',
                        'options' => [
                            'adherent chromium oxide layer کافی تشکیل نمی شود و corrosion resistance افت می کند.',
                            'chromium carbides بیش از حد تشکیل شده و steel بدون carbon می شود.',
                            'alloy به shape-memory Ni-Ti تبدیل می شود.',
                            'passivation بیش از حد رخ می دهد و soldering آسان تر می شود.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: اگر chromium کمتر از حدود 13% باشد، adherent chromium oxide layer کافی تشکیل نمی شود و corrosion resistance کاهش می یابد. رد گزینه ب: carbide formation بیش از حد بیشتر با chromium بالای 28% یا carbon کنترل نشده مرتبط است. رد گزینه ج: stainless steel به Ni-Ti shape-memory تبدیل نمی شود. رد گزینه د: passivation بیش از حد در این حالت رخ نمی دهد.',
                    ],
                    [
                        'question' => 'درباره Pd-Ag alloys برای ceramic-metal restorations، کدام گزاره دقیق تر است؟',
                        'options' => [
                            'بدون gold، با silver نسبتاً زیاد، lower density نسبت به Au-Pd-Ag و گزارش greening در برخی ceramics',
                            'gold بسیار زیاد، yellow color و hardening با FePt3 precipitate',
                            'chromium بالا، solution hardening و کاربرد اصلی در endodontic files',
                            'titanium بالا، high oxidation در casting و no silver',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: Pd-Ag alloys gold ندارند، silver نسبتاً زیادی دارند، density پایین تری از Au-Pd-Ag دارند و در برخی ceramics مشکل greening گزارش شده است. رد گزینه ب: gold/FePt3 مربوط به Au-Pt-Pd است. رد گزینه ج: chromium و endodontic files مربوط به stainless/base-metal alloys است. رد گزینه د: titanium بالا و oxidation casting مربوط به Ti alloys است.',
                    ],
                    [
                        'question' => 'در wrought noble alloys، چرا grain refinerهایی مانند Ir/Ru معمولاً ضروری نیستند؟',
                        'options' => [
                            'چون alloys با cold work به final forms می رسند و grain refinement casting نقش اصلی ندارد.',
                            'چون wrought alloys هیچ گاه heated یا soldered نمی شوند.',
                            'چون همه wrought alloys base-metal و بدون noble content هستند.',
                            'چون solidus آن ها همیشه زیر 400°C است.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: wrought alloys با cold work به forms نهایی می رسند؛ بنابراین grain refinement casting با Ir/Ru مانند casting alloys ضروری نیست. رد گزینه ب: wrought alloys ممکن است soldered یا cast-to شوند. رد گزینه ج: همه wrought alloys base-metal نیستند؛ بیشترشان high-noble هستند. رد گزینه د: solidus آن ها بسیار بالاتر از 400°C است.',
                    ],
                    [
                        'question' => 'برای بهبود osseointegration در machined titanium implants، کدام سطح سازی در متن ذکر شده است؟',
                        'options' => [
                            'grit blasting با metal oxide یا hydroxyapatite و همچنین plasma spraying/coating با titanium یا hydroxyapatite',
                            'افزودن γ2 به سطح implant و etching با mercury',
                            'ایجاد black sulfide layer با pure silver',
                            'تشکیل chromium carbides در grain boundaries سطح implant',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: برای افزایش contact surface area و osseointegration، grit blasting با metal oxide/hydroxyapatite و plasma spraying/coating با titanium یا hydroxyapatite ذکر شده است. رد گزینه ب: γ2 و mercury مربوط به amalgam است. رد گزینه ج: black sulfide مربوط به silver در mouth است. رد گزینه د: chromium carbide مربوط به stainless/base-metal alloy corrosion issues است.',
                    ],
                    [
                        'question' => 'کاربرد بالینی low elastic modulus و high resiliency در Ni-Ti orthodontic wire چیست؟',
                        'options' => [
                            'اعمال نیروهای پایین تر و یکنواخت تر با activations و working range بیشتر',
                            'افزایش solderability و امکان bends تیز و complete loop',
                            'کاهش springback و نیاز بیشتر به active adjustments کوتاه مدت',
                            'افزایش stiffness تا سطح stainless steel و کاهش stored energy',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: Ni-Ti به دلیل low elastic modulus و high resiliency نیروهای lower و more constant با activationها و working range بیشتر فراهم می کند. رد گزینه ب: Nitinol brittle است و نمی توان آن را solder/weld کرد یا bends تیز/complete loop داد. رد گزینه ج: Ni-Ti high springback دارد، نه کاهش آن. رد گزینه د: stiffness آن کمتر از stainless steel است و stored energy/resiliency بالاتر است.',
                    ],
                    [
                        'question' => 'مقایسه base-metal casting alloys با cast gold alloy types I-IV از نظر melting range و density چگونه است؟',
                        'options' => [
                            'base-metal alloys melting temperature بالاتر دارند و density آن ها تقریباً نصف density بسیاری gold alloys است.',
                            'base-metal alloys melting temperature پایین تر و density دو برابر gold alloys دارند.',
                            'هر دو melting range و density یکسان دارند و تفاوت فقط رنگ است.',
                            'base-metal alloys به دلیل density بالا برای maxillary prosthesis سنگین ترند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: base-metal casting alloys melting range حدود 1150 تا 1500°C دارند، بالاتر از gold types I-IV، و density آن ها حدود 7 تا 8 g/cm3 یعنی تقریباً نصف بسیاری dental gold alloys است. رد گزینه ب: جهت تفاوت ها برعکس است. رد گزینه ج: melting range و density یکسان نیستند. رد گزینه د: lower density باعث کاهش وزن bulky maxillary prostheses می شود.',
                    ],
                    [
                        'question' => 'در cast base-metal alloys برای removable dental prostheses، چرا عناصر minor مثل carbon و molybdenum مهم اند؟',
                        'options' => [
                            'چون خواص فیزیکی بیش از principal elements به حضور و میزان برخی minor alloying elements حساس است.',
                            'چون chromium، cobalt و nickel هیچ نقشی در ترکیب ندارند.',
                            'چون carbon همیشه باید صفر باشد تا carbide strengthening رخ دهد.',
                            'چون molybdenum فقط رنگ yellow ایجاد می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید اگرچه Cr/Co/Ni بیشتر وزن را تشکیل می دهند، خواص به minor elements مانند carbon و molybdenum بسیار حساس است. رد گزینه ب: Cr/Co/Ni principal elements هستند و نقش دارند. رد گزینه ج: carbon صفر شرط strengthening نیست؛ مقدار آن باید دقیق کنترل شود. رد گزینه د: molybdenum برای strength و در برخی alloys کاهش expansion مطرح است، نه yellow color.',
                    ],
                    [
                        'question' => 'good bond در ceramic-metal restoration بیشتر با کدام عوامل مرتبط است؟',
                        'options' => [
                            'interactions ceramic با metal oxides روی سطح metal و roughness coping',
                            'mercury diffusion به Ag3Sn و تشکیل γ1 matrix',
                            'cold work fibrous structure و عدم وجود oxide',
                            'black sulfide formation روی pure silver',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: good ceramic-metal bond از interactions ceramic با metal oxides و roughness سطح coping حاصل می شود. رد گزینه ب: mercury diffusion و γ1 مربوط به amalgam است. رد گزینه ج: cold work fibrous structure مربوط به wrought alloys است و oxide bond را حذف نمی کند. رد گزینه د: black sulfide روی silver علت ceramic-metal bonding نیست.',
                    ],
                    [
                        'question' => 'stress-relieving در 18–8 stainless steel orthodontic appliance چگونه باید انجام شود؟',
                        'options' => [
                            'حدود 400°C تا 500°C برای 5 تا 120 ثانیه؛ نمونه میانگین 1 دقیقه در 450°C',
                            'بالاتر از 650°C به مدت 30 دقیقه؛ برای بازگردانی خواص پس از annealing',
                            '883°C برای تبدیل α به β؛ سپس quenching در water',
                            '1700°C در vacuum؛ برای حذف chromium oxide layer',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: stress-relieving اگر انجام شود در 400°C تا 500°C برای 5 تا 120 ثانیه است؛ یک treatment متوسط 1 دقیقه در 450°C برای orthodontic appliance ذکر شده است. رد گزینه ب: بالاتر از 650°C annealing/softening و کاهش properties ایجاد می کند و قابل بازگردانی نیست. رد گزینه ج: 883°C transformation titanium است. رد گزینه د: 1700°C مربوط به melting titanium است، نه stainless stress relief.',
                    ],
                    [
                        'question' => 'ترکیب corrosion و wear در Ni-Cr alloys چه پیامد خاصی در متن دارد؟',
                        'options' => [
                            'release فلزاتی مثل Ni می تواند تا حدود سه برابر corrosion alone افزایش یابد.',
                            'release فلزات به صفر می رسد، چون wear surface را passivate می کند.',
                            'فقط gold آزاد می شود و nickel ثابت می ماند.',
                            'این پدیده فقط در amalgam و نه base-metal alloys رخ می دهد.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن می گوید occlusal rubbing همراه corrosion در Ni-Cr alloys می تواند release mass of metal ions مانند Ni را تا سه برابر corrosion alone افزایش دهد. رد گزینه ب: wear release را صفر نمی کند. رد گزینه ج: در Ni-Cr alloy، nickel مطرح است نه gold. رد گزینه د: این پدیده در base-metal alloys نیز مطرح شده است.',
                    ],
                    [
                        'question' => 'دلیل پذیرش گسترده cobalt-chromium alloys در کاربردهای surgical/orthopedic و oral surgical طبق متن چیست؟',
                        'options' => [
                            'low solubility و electrogalvanic action کم، inert behavior و نبود inflammatory response قابل توجه',
                            'وجود gold بالا و carat/fineness مناسب برای bone plates',
                            'شکل پذیری shape-memory و transformation martensitic/austenitic',
                            'واکنش شدید با saliva که باعث antibacterial layer می شود',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: favorable tissue response Co-Cr surgical alloys به low solubility و electrogalvanic action کم، inert behavior و نبود inflammatory response نسبت داده شده است. رد گزینه ب: Co-Cr surgical alloys gold-based نیستند. رد گزینه ج: shape-memory مربوط به Ni-Ti است. رد گزینه د: واکنش شدید با saliva به عنوان مزیت ذکر نشده است.',
                    ],
                    [
                        'question' => 'در commercially pure titanium، تفاوت gradeها بیشتر به کدام عناصر مربوط است و transformation اصلی در چه دمایی رخ می دهد؟',
                        'options' => [
                            'oxygen و iron؛ α به β در حدود 883°C',
                            'carbon و chromium؛ ferrite به austenite در 450°C',
                            'silver و palladium؛ yellow به white در 10 wt% Pd',
                            'mercury و tin؛ γ به γ1 در 24 ساعت',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: CP Ti grades عمدتاً با oxygen و iron content متفاوت اند و در 883°C از α hexagonal close-packed به β body-centered cubic تبدیل می شود. رد گزینه ب: ferrite/austenite و carbon/chromium مربوط به steels است. رد گزینه ج: silver/palladium مربوط به noble alloy color است. رد گزینه د: mercury/tin و γ1 مربوط به amalgam است.',
                    ],
                    [
                        'question' => 'five-axis machining در CAD/CAM فلزی چه مزیتی دارد؟',
                        'options' => [
                            'ایجاد embrasures، undercuts و geometries پیچیده بدون trimming/adjustment دستی گسترده',
                            'حذف نیاز به scanning و software در طراحی frameworks',
                            'تبدیل powder به wrought fiber structure از راه cold work',
                            'ایجاد shape-memory در stainless steel brackets',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: five-axis machining امکان ایجاد embrasures، undercuts و geometries پیچیده را بدون manual trimming/adjustment گسترده فراهم می کند. رد گزینه ب: CAD/CAM به scanning/software وابسته است. رد گزینه ج: powder-to-fibrous cold work توصیف five-axis machining نیست. رد گزینه د: shape-memory در stainless steel ایجاد نمی کند.',
                    ],
                    [
                        'question' => 'در Ni-Cr alloys برای ceramic-metal restorations، کدام نقش عناصر درست تر است؟',
                        'options' => [
                            'chromium برای tarnish/corrosion resistance، aluminum برای Ni3Al precipitates، و molybdenum برای کاهش thermal coefficient of expansion',
                            'zinc برای passivation، gallium برای حذف porcelain bond، و gold برای carbide formation',
                            'mercury برای matrix formation، tin برای γ2، و copper برای eutectic spheres',
                            'titanium برای black sulfide، carbon برای کاهش hardness، و silver برای sensitization',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: در Ni-Cr alloys، chromium corrosion/tarnish resistance می دهد، aluminum با Ni3Al precipitates strengthening ایجاد می کند، و molybdenum thermal coefficient of expansion را کاهش می دهد. رد گزینه ب: zinc/gallium/gold چنین نقش هایی در Ni-Cr context ندارند. رد گزینه ج: mercury/tin/copper مربوط به amalgam است. رد گزینه د: sensitization به chromium carbide در stainless steel مربوط است، نه نقش اصلی Ti/C/Ag در Ni-Cr.',
                    ],
                    [
                        'question' => 'در wrought alloys دارای ordered phase hardening، hard condition چه الگوی خواصی ایجاد می کند؟',
                        'options' => [
                            'strength و hardness بیشتر، اما elongation کمتر نسبت به soft condition',
                            'strength کمتر، hardness کمتر و elongation بیشتر از soft condition',
                            'فقط color تغییر می کند و خواص مکانیکی ثابت می ماند.',
                            'solidus به زیر دمای اتاق می رسد و alloy melt می شود.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: ordered phase در hard condition strength و hardness را افزایش می دهد، اما elongation را نسبت به soft condition کاهش می دهد. رد گزینه ب: این الگو برعکس متن است. رد گزینه ج: properties مکانیکی با hardening تغییر می کنند. رد گزینه د: solidus به زیر room temperature نمی رسد.',
                    ],
                    [
                        'question' => 'در کار با Be-containing alloys، کدام اقدام عملی با توصیه های متن سازگار است؟',
                        'options' => [
                            'استفاده از efficient local exhaust/filtration و adequate general ventilation هنگام casting/finishing/polishing',
                            'انجام finishing خشک داخل دهان بدون evacuation برای کاهش particulate spread',
                            'افزودن beryllium تا بالاتر از 2 wt% برای مطابقت با ISO standard',
                            'حذف کامل ventilation چون خطر فقط برای بیمار و نه laboratory personnel است',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: متن استفاده از local exhaust/filtration کارآمد و ventilation عمومی کافی را هنگام casting, finishing و polishing Be-containing alloys توصیه می کند. رد گزینه ب: finishing خشک داخل دهان exposure particulate را افزایش می دهد و با توصیه متن ناسازگار است. رد گزینه ج: ISO Be را به 0.02 wt% محدود می کند، نه بالاتر از 2 wt%. رد گزینه د: laboratory personnel بیشترین risk exposure را دارند.',
                    ],
                    [
                        'question' => 'چرا alloying titanium با Pd یا Cu می تواند casting را از یک جنبه آسان تر کند؟',
                        'options' => [
                            'melting point را می تواند تا حدود 1350°C، نزدیک Ni-Cr و Co-Cr alloys، کاهش دهد.',
                            'density را به اندازه gold alloys بالا می برد و oxygen affinity را حذف می کند.',
                            'titanium را به 18–8 stainless steel تبدیل می کند.',
                            'β-transition temperature را به 37°C می رساند و shape-memory ایجاد می کند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: Ti-Pd و Ti-Cu alloys می توانند melting point حدود 1350°C داشته باشند و casting temperature را به محدوده Ni-Cr/Co-Cr نزدیک کنند. رد گزینه ب: alloying با Pd/Cu oxygen affinity را حذف نمی کند. رد گزینه ج: titanium با این alloying به stainless steel تبدیل نمی شود. رد گزینه د: shape-memory وابسته به Ni-Ti است.',
                    ],
                    [
                        'question' => 'طبق جدول orthodontic wires، ترتیب تقریبی elastic modulus در tension چگونه است؟',
                        'options' => [
                            'stainless steel بالاتر از beta-titanium و beta-titanium بالاتر از nickel-titanium',
                            'nickel-titanium بالاتر از stainless steel و stainless steel بالاتر از beta-titanium',
                            'beta-titanium بالاتر از stainless steel و nickel-titanium پایین ترین نیست',
                            'هر سه alloy elastic modulus یکسان دارند.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: جدول orthodontic wires elastic modulus tensile را برای 18-8 stainless steel حدود 134 GPa، beta-titanium حدود 68.6 GPa و nickel-titanium حدود 28.4 GPa نشان می دهد. رد گزینه ب: Ni-Ti پایین ترین modulus را دارد، نه بالاترین. رد گزینه ج: beta-titanium از stainless steel پایین تر است. رد گزینه د: مقادیر سه alloy برابر نیستند.',
                    ],
                    [
                        'question' => 'در میان noble alloys برای ceramic-metal restorations، کدام گزینه درباره color درست تر است؟',
                        'options' => [
                            'Au-Pt-Pd yellow است، در حالی که Au-Pd، Au-Pd-Ag، Pd-Ag و Pd-Cu سفید گزارش شده اند.',
                            'همه five types yellow هستند، چون همگی noble-metal دارند.',
                            'Pd-Cu yellow است و Au-Pt-Pd white است.',
                            'Pd-Ag به دلیل نبود gold حتماً black است.',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: جدول noble alloys for ceramic-metal restorations، Au-Pt-Pd را yellow و Au-Pd، Au-Pd-Ag، Pd-Ag و Pd-Cu را white گزارش می کند. رد گزینه ب: noble بودن به معنای yellow بودن همه types نیست. رد گزینه ج: رنگ ها برعکس متن هستند. رد گزینه د: absence of gold باعث black بودن Pd-Ag نمی شود.',
                    ],
                    [
                        'question' => 'کدام کاربرد با Box مربوط به cast و wrought base-metal alloys هماهنگ است؟',
                        'options' => [
                            'wrought nickel-titanium alloys برای orthodontic wires و endodontic files',
                            'cast nickel-chromium alloys برای pit and fissure sealants',
                            'wrought stainless steel alloys برای amalgam core buildup matrix phase',
                            'cast titanium فقط برای denture base acrylic teeth',
                        ],
                        'correctIndex' => 0,
                        'explanation' => '**پاسخ درست:** گزینه الف
دلیل درست بودن گزینه الف: Box 10.1 wrought nickel-titanium alloys را برای orthodontic wires و endodontic files ذکر می کند. رد گزینه ب: pit and fissure sealants کاربرد cast Ni-Cr alloys نیست. رد گزینه ج: stainless steel alloys برای orthodontic wires/brackets، endodontic instruments و preformed crowns ذکر شده اند، نه amalgam matrix phase. رد گزینه د: cast titanium کاربردهایی مانند crowns، fixed partial prosthesis، RPD framework و implants دارد؛ محدود به denture base acrylic teeth نیست.',
                    ],
                ],
            ],
        ],
    ];
}
