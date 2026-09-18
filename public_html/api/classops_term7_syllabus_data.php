<?php
declare(strict_types=1);

/**
 * Structured metadata transcribed from the corrected Term 7 course-syllabus PDFs.
 * This is metadata only. Academic timetable recurrence remains canonical in
 * academic_term7.php and must not be inferred or overwritten from these PDFs.
 */
function classops_term7_syllabus_source_catalog(): array
{
    static $catalog = null;
    if (is_array($catalog)) {
        return $catalog;
    }
    $catalog = json_decode(<<<'JSON'
{
  "orthodontics-theory-1": {
    "version": "1405-1406-1.corrected.1",
    "sourceTiming": {
      "default": {"start": "12:30", "end": "13:30", "appliesToVirtual": true}
    },
    "eventSlugs": [
      "orthodontics-theory-1"
    ],
    "courseTitle": "ارتودنسی نظری ۱",
    "sourceCourseTitle": "ارتودنسی نظری ۱",
    "sourceFile": "ارتودانتیکس نظری ۱.pdf",
    "courseCoordinator": "دکتر احمد سوداگر",
    "sessions": [
      {
        "sessionNumber": 1,
        "sessionNumbers": [
          1
        ],
        "dates": [
          "1405/06/30"
        ],
        "title": "تعریف، تاریخچه، اپیدمیولوژی و ترمینولوژی ارتودنسی",
        "instructor": "دکتر عرب",
        "references": [
          "Proffit WR, Fields HW, Sarver DM. Contemporary Orthodontics. St Louis: Mosby"
        ],
        "sessionMode": "virtual",
        "sourcePage": 1
      },
      {
        "sessionNumber": 2,
        "sessionNumbers": [
          2
        ],
        "dates": [
          "1405/07/06"
        ],
        "title": "رشد و نمو قبل و بعد از تولد",
        "instructor": "دکتر قدیریان",
        "references": [
          "Proffit WR, Fields HW, Sarver DM. Contemporary Orthodontics. St Louis: Mosby"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 3,
        "sessionNumbers": [
          3
        ],
        "dates": [
          "1405/07/13"
        ],
        "title": "رشد و نمو قبل و بعد از تولد",
        "instructor": "دکتر قدیریان",
        "references": [
          "Bishara SE. Textbook of Orthodontics",
          "Enlow DH, Hans MG. Essentials of Facial Growth"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 4,
        "sessionNumbers": [
          4
        ],
        "dates": [
          "1405/07/20"
        ],
        "title": "رشد و نمو قبل و بعد از تولد",
        "instructor": "دکتر قدیریان",
        "references": [],
        "sessionMode": "virtual",
        "sourcePage": 1
      },
      {
        "sessionNumber": 5,
        "sessionNumbers": [
          5
        ],
        "dates": [
          "1405/07/27"
        ],
        "title": "رشد و تکامل اکلوژن",
        "instructor": "دکتر میرهاشمی",
        "references": [
          "Proffit WR, Fields HW, Sarver DM. Contemporary Orthodontics. St Louis: Mosby"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 6,
        "sessionNumbers": [
          6
        ],
        "dates": [
          "1405/07/27"
        ],
        "title": "رشد و تکامل اکلوژن",
        "instructor": "دکتر میرهاشمی",
        "references": [],
        "sessionMode": "virtual",
        "sourcePage": 1
      },
      {
        "sessionNumber": 7,
        "sessionNumbers": [
          7
        ],
        "dates": [
          "1405/08/04"
        ],
        "title": "اصول کلی معاینات کلینیکی",
        "instructor": "دکتر قدیریان",
        "references": [
          "Proffit WR, Fields HW, Sarver DM. Contemporary Orthodontics, Chapter 6"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 8,
        "sessionNumbers": [
          8
        ],
        "dates": [
          "1405/08/11"
        ],
        "title": "اصول کلی معاینات کلینیکی",
        "instructor": "دکتر قدیریان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 9,
        "sessionNumbers": [
          9
        ],
        "dates": [
          "1405/08/18"
        ],
        "title": "آنالیزهای قالب دندانی مطالعه",
        "instructor": "دکتر گرامی",
        "references": [
          "Proffit WR, Fields HW, Sarver DM. Contemporary Orthodontics. St Louis: Mosby"
        ],
        "sessionMode": "virtual",
        "sourcePage": 1
      },
      {
        "sessionNumber": 10,
        "sessionNumbers": [
          10
        ],
        "dates": [
          "1405/08/25"
        ],
        "title": "آنالیزهای قالب دندانی مطالعه",
        "instructor": "دکتر گرامی",
        "references": [
          "Proffit WR, Fields HW, Sarver DM. Contemporary Orthodontics. St Louis: Mosby"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 11,
        "sessionNumbers": [
          11
        ],
        "dates": [
          "1405/09/02"
        ],
        "title": "آنالیز سفالومتری",
        "instructor": "دکتر سرمدی",
        "references": [
          "Radiographic Cephalometry - Alexander Jacobson",
          "Contemporary Orthodontics - William Proffit",
          "Orthodontic Diagnosis - Thomas Rakosi / Thomas M. Graber",
          "کتاب ملی ارتودانتیکس"
        ],
        "sessionMode": "in_person",
        "sourcePage": 2
      },
      {
        "sessionNumber": 12,
        "sessionNumbers": [
          12
        ],
        "dates": [
          "1405/09/09"
        ],
        "title": "آنالیز سفالومتری",
        "instructor": "دکتر سرمدی",
        "references": [
          "Radiographic Cephalometry - Alexander Jacobson",
          "Contemporary Orthodontics - William Proffit",
          "Orthodontic Diagnosis - Thomas Rakosi / Thomas M. Graber",
          "کتاب ملی ارتودانتیکس"
        ],
        "sessionMode": "in_person",
        "sourcePage": 2
      },
      {
        "sessionNumber": 13,
        "sessionNumbers": [
          13
        ],
        "dates": [
          "1405/09/16"
        ],
        "title": "آنالیز سفالومتری",
        "instructor": "دکتر سرمدی",
        "references": [
          "Radiographic Cephalometry - Alexander Jacobson",
          "Contemporary Orthodontics - William Proffit",
          "Orthodontic Diagnosis - Thomas Rakosi / Thomas M. Graber",
          "کتاب ملی ارتودانتیکس"
        ],
        "sessionMode": "virtual",
        "sourcePage": 2
      },
      {
        "sessionNumber": 14,
        "sessionNumbers": [
          14
        ],
        "dates": [
          "1405/09/23"
        ],
        "title": "آنالیز فتوگرافی و مچ دست و مهره‌های گردن",
        "instructor": "دکتر صفار",
        "references": [
          "اسلایدهای کلاس"
        ],
        "sessionMode": "virtual",
        "sourcePage": 2
      },
      {
        "sessionNumber": 15,
        "sessionNumbers": [
          15
        ],
        "dates": [
          "1405/09/23"
        ],
        "title": "اتیولوژی مال‌اکلوژن‌ها",
        "instructor": "دکتر تنباکوچی",
        "references": [
          "Proffit WR, Fields HW, Sarver DM. Contemporary Orthodontics. St Louis: Mosby"
        ],
        "sessionMode": "in_person",
        "sourcePage": 2
      },
      {
        "sessionNumber": 16,
        "sessionNumbers": [
          16
        ],
        "dates": [
          "1405/10/06"
        ],
        "title": "اتیولوژی مال‌اکلوژن‌ها",
        "instructor": "دکتر تنباکوچی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 2
      }
    ]
  },
  "endodontics-theory-1": {
    "version": "1405-1406-1.corrected.1",
    "sourceTiming": {
      "singleSessionDay": {"start": "08:30", "end": "09:30", "appliesToVirtual": true},
      "multiSessionDay": {"start": "08:30", "end": "10:30", "appliesToVirtual": true}
    },
    "eventSlugs": [
      "endodontics-theory-1"
    ],
    "courseTitle": "اندودانتیکس نظری ۱",
    "sourceCourseTitle": "اندودانتیکس نظری ۱",
    "sourceFile": "اندودانتیکس نظری ۱.pdf",
    "sessions": [
      {
        "sessionNumber": null,
        "sessionNumbers": [
          1,
          2
        ],
        "dates": [
          "1405/07/02"
        ],
        "title": "عوامل مؤثر بر طرح درمان و ارزیابی میزان دشواری درمان‌های اندودانتیکس",
        "instructor": "دکتر صراف",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "sessionLabel": "جلسات ۱ و ۲"
      },
      {
        "sessionNumber": 3,
        "sessionNumbers": [
          3
        ],
        "dates": [
          "1405/07/09"
        ],
        "title": "عوامل مؤثر بر طرح درمان و ارزیابی میزان دشواری درمان‌های اندودانتیکس",
        "instructor": "دکتر صراف",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": null,
        "sessionNumbers": [
          4,
          5
        ],
        "dates": [
          "1405/07/16"
        ],
        "title": "ارزیابی بیمار و ملاحظات سیستمیک در درمان‌های اندودانتیک",
        "instructor": "دکتر نوری",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "sessionLabel": "جلسات ۴ و ۵"
      },
      {
        "sessionNumber": 6,
        "sessionNumbers": [
          6
        ],
        "dates": [
          "1405/07/23"
        ],
        "title": "رادیولوژی در اندودانتیکس",
        "instructor": "دکتر اسدیان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": null,
        "sessionNumbers": [
          7,
          8
        ],
        "dates": [
          "1405/07/30"
        ],
        "title": "بی‌حسی موضعی در اندودانتیکس",
        "instructor": "دکتر مروی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "sessionLabel": "جلسات ۷ و ۸"
      },
      {
        "sessionNumber": null,
        "sessionNumbers": [
          9,
          10
        ],
        "dates": [
          "1405/08/07"
        ],
        "title": "اورژانس‌های اندودانتیکس",
        "instructor": "دکتر غبرائی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "sessionLabel": "جلسات ۹ و ۱۰"
      },
      {
        "sessionNumber": 11,
        "sessionNumbers": [
          11
        ],
        "dates": [
          "1405/08/14"
        ],
        "title": "دارودرمانی در اندودانتیکس",
        "instructor": "دکتر خوشخونژاد",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 12,
        "sessionNumbers": [
          12
        ],
        "dates": [
          "1405/08/14"
        ],
        "title": "ضایعات اندودانتیک - پریودنتال",
        "instructor": "دکتر خوشخونژاد",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": null,
        "sessionNumbers": [
          13,
          14
        ],
        "dates": [
          "1405/08/21"
        ],
        "title": "موفقیت و عدم موفقیت در درمان‌های اندودانتیک",
        "instructor": "دکتر شکوهی‌نژاد",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "sessionLabel": "جلسات ۱۳ و ۱۴"
      },
      {
        "sessionNumber": 15,
        "sessionNumbers": [
          15
        ],
        "dates": [
          "1405/08/28"
        ],
        "title": "اندودانتیکس در بیماران مسن",
        "instructor": "دکتر صراف",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      }
    ]
  },
  "diagnostic-dentistry-3": {
    "version": "1405-1406-1.corrected.1",
    "sourceTiming": {
      "byEventSlug": {
        "diagnostic-dentistry-3-sun": {"start": "07:30", "end": "08:30", "appliesToVirtual": true},
        "diagnostic-dentistry-3-mon": {"start": "13:15", "end": "14:15", "appliesToVirtual": true}
      }
    },
    "eventSlugs": [
      "diagnostic-dentistry-3-sun",
      "diagnostic-dentistry-3-mon"
    ],
    "courseTitle": "دندانپزشکی تشخیصی ۳",
    "sourceCourseTitle": "دندانپزشکی تشخیصی ۳",
    "sourceFile": "دندان‌پزشکی تشخیصی ۳.pdf",
    "sessions": [
      {
        "sessionNumber": 1,
        "sessionNumbers": [
          1
        ],
        "dates": [
          "1405/06/29"
        ],
        "title": "ضایعات اگزوفیتیک خارج استخوانی",
        "instructor": "دکتر پورشهیدی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 2,
        "sessionNumbers": [
          2
        ],
        "dates": [
          "1405/06/30"
        ],
        "title": "ضایعات اگزوفیتیک خارج استخوانی",
        "instructor": "دکتر پورشهیدی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 3,
        "sessionNumbers": [
          3
        ],
        "dates": [
          "1405/07/05"
        ],
        "title": "ضایعات اگزوفیتیک خارج استخوانی",
        "instructor": "دکتر پورشهیدی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 4,
        "sessionNumbers": [
          4
        ],
        "dates": [
          "1405/07/06"
        ],
        "title": "ضایعات اگزوفیتیک خارج استخوانی",
        "instructor": "دکتر پورشهیدی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 5,
        "sessionNumbers": [
          5
        ],
        "dates": [
          "1405/07/12"
        ],
        "title": "ضایعات واکنشی",
        "instructor": "دکتر درخشان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 6,
        "sessionNumbers": [
          6
        ],
        "dates": [
          "1405/07/13"
        ],
        "title": "ضایعات واکنشی",
        "instructor": "دکتر درخشان",
        "references": [],
        "sessionMode": "in_person_quiz",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 7,
        "sessionNumbers": [
          7
        ],
        "dates": [
          "1405/07/19"
        ],
        "title": "ضایعات واکنشی",
        "instructor": "دکتر درخشان",
        "references": [],
        "sessionMode": "flipped",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 8,
        "sessionNumbers": [
          8
        ],
        "dates": [
          "1405/07/20"
        ],
        "title": "ضایعات واکنشی",
        "instructor": "دکتر درخشان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 9,
        "sessionNumbers": [
          9
        ],
        "dates": [
          "1405/07/26"
        ],
        "title": "ضایعات سفید و قرمز",
        "instructor": "دکتر منصوریان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 10,
        "sessionNumbers": [
          10
        ],
        "dates": [
          "1405/07/27"
        ],
        "title": "ضایعات سفید و قرمز",
        "instructor": "دکتر منصوریان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 11,
        "sessionNumbers": [
          11
        ],
        "dates": [
          "1405/08/03"
        ],
        "title": "ضایعات سفید و قرمز",
        "instructor": "دکتر منصوریان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 12,
        "sessionNumbers": [
          12
        ],
        "dates": [
          "1405/08/04"
        ],
        "title": "ضایعات سفید و قرمز",
        "instructor": "دکتر منصوریان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 13,
        "sessionNumbers": [
          13
        ],
        "dates": [
          "1405/08/10"
        ],
        "title": "ضایعات خوش‌خیم اپیتلیالی",
        "instructor": "دکتر مهدوی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 14,
        "sessionNumbers": [
          14
        ],
        "dates": [
          "1405/08/11"
        ],
        "title": "ضایعات خوش‌خیم اپیتلیالی",
        "instructor": "دکتر مهدوی",
        "references": [],
        "sessionMode": "in_person_quiz",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 15,
        "sessionNumbers": [
          15
        ],
        "dates": [
          "1405/08/17"
        ],
        "title": "ضایعات پیگمانته",
        "instructor": "دکتر شیرازیان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 16,
        "sessionNumbers": [
          16
        ],
        "dates": [
          "1405/08/18"
        ],
        "title": "ضایعات پیگمانته",
        "instructor": "دکتر شیرازیان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 17,
        "sessionNumbers": [
          17
        ],
        "dates": [
          "1405/08/24"
        ],
        "title": "ضایعات پیگمانته",
        "instructor": "دکتر شیرازیان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "میان‌ترم"
      },
      {
        "sessionNumber": 18,
        "sessionNumbers": [
          18
        ],
        "dates": [
          "1405/08/25"
        ],
        "title": "ضایعات پیش‌بدخیم",
        "instructor": "دکتر شکیب",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 19,
        "sessionNumbers": [
          19
        ],
        "dates": [
          "1405/09/01"
        ],
        "title": "ضایعات پیش‌بدخیم",
        "instructor": "دکتر شکیب",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 20,
        "sessionNumbers": [
          20
        ],
        "dates": [
          "1405/09/02"
        ],
        "title": "ضایعات پیش‌بدخیم",
        "instructor": "دکتر شکیب",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 21,
        "sessionNumbers": [
          21
        ],
        "dates": [
          "1405/09/08"
        ],
        "title": "تظاهرات بالینی ضایعات پیش‌بدخیم",
        "instructor": "دکتر شیخ‌بهایی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 22,
        "sessionNumbers": [
          22
        ],
        "dates": [
          "1405/09/09"
        ],
        "title": "تظاهرات بالینی ضایعات پیش‌بدخیم",
        "instructor": "دکتر شیرازیان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 23,
        "sessionNumbers": [
          23
        ],
        "dates": [
          "1405/09/15"
        ],
        "title": "ضایعات بدخیم اپیتلیالی",
        "instructor": "دکتر شکیب",
        "references": [],
        "sessionMode": "conditional_virtual",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 24,
        "sessionNumbers": [
          24
        ],
        "dates": [
          "1405/09/16"
        ],
        "title": "ضایعات بدخیم اپیتلیالی",
        "instructor": "دکتر شکیب",
        "references": [],
        "sessionMode": "conditional_virtual",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 25,
        "sessionNumbers": [
          25
        ],
        "dates": [
          "1405/09/22"
        ],
        "title": "ضایعات بدخیم اپیتلیالی",
        "instructor": "دکتر شکیب",
        "references": [],
        "sessionMode": "conditional_virtual",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 26,
        "sessionNumbers": [
          26
        ],
        "dates": [
          "1405/09/23"
        ],
        "title": "ضایعات بدخیم اپیتلیالی",
        "instructor": "دکتر شکیب",
        "references": [],
        "sessionMode": "in_person_quiz",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 27,
        "sessionNumbers": [
          27
        ],
        "dates": [
          "1405/09/29"
        ],
        "title": "تظاهرات بالینی ضایعات بدخیم اپیتلیالی",
        "instructor": "دکتر کوپایی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 28,
        "sessionNumbers": [
          28
        ],
        "dates": [
          "1405/09/30"
        ],
        "title": "ضایعات خوش‌خیم مزانشیمی",
        "instructor": "دکتر مرادزاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 29,
        "sessionNumbers": [
          29
        ],
        "dates": [
          "1405/10/06"
        ],
        "title": "ضایعات خوش‌خیم مزانشیمی",
        "instructor": "دکتر مرادزاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 30,
        "sessionNumbers": [
          30
        ],
        "dates": [
          "1405/10/07"
        ],
        "title": "ضایعات بدخیم مزانشیمی",
        "instructor": "دکتر مرادزاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 31,
        "sessionNumbers": [
          31
        ],
        "dates": [
          "1405/10/13"
        ],
        "title": "ضایعات بدخیم مزانشیمی",
        "instructor": "دکتر مرادزاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 32,
        "sessionNumbers": [
          32
        ],
        "dates": [
          "1405/10/14"
        ],
        "title": "انواع بیوپسی و اصول برخورد با ضایعات پاتولوژی",
        "instructor": "دکتر قراجه‌ای",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      },
      {
        "sessionNumber": 33,
        "sessionNumbers": [
          33
        ],
        "dates": [
          "1405/10/20"
        ],
        "title": "انواع بیوپسی و اصول برخورد با ضایعات پاتولوژی",
        "instructor": "دکتر قراجه‌ای",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "assessmentPart": "پایان‌ترم"
      }
    ]
  },
  "research-methods-2": {
    "version": "1405-1406-1.corrected.1",
    "rotationRelative": true,
    "rotationSourceAnchor": "1405/06/28",
    "sourceTiming": {
      "default": {"start": "13:00", "end": "15:30", "appliesToVirtual": false}
    },
    "eventSlugs": [
      "research-methods-2-practical"
    ],
    "courseTitle": "روش تحقیق ۲",
    "sourceCourseTitle": "روش‌شناسی تحقیق ۲",
    "sourceFile": "روش‌شناسی تحقیق ۲.pdf",
    "courseCoordinator": "دکتر شیما یونس‌پور",
    "sessions": [
      {
        "sessionNumber": 1,
        "sessionNumbers": [
          1
        ],
        "dates": [
          "1405/06/28"
        ],
        "title": "مقدمه و معرفی دوره و منابع؛ آشنایی با بخش‌های پروپوزال، پژوهشیار و گروه‌بندی دانشجویان",
        "instructor": "دکتر یونس‌پور و همه اساتید؛ دکتر یونس‌پور و دکتر صراف‌زاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "segments": [
          {
            "time": "13:00–14:15",
            "title": "مقدمه، معرفی دوره و معرفی منابع"
          },
          {
            "time": "14:15–15:30",
            "title": "آشنایی با بخش‌های پروپوزال، معرفی پژوهشیار و گروه‌بندی دانشجویان"
          }
        ]
      },
      {
        "sessionNumber": 2,
        "sessionNumbers": [
          2
        ],
        "dates": [
          "1405/07/01"
        ],
        "title": "مروری بر منابع علمی و روش‌های جستجو در پایگاه‌های داده",
        "instructor": "دکتر یونس‌پور",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 3,
        "sessionNumbers": [
          3
        ],
        "dates": [
          "1405/07/04"
        ],
        "title": "انتخاب موضوع و بیان مسئله؛ کار عملی انتخاب موضوع و بحث درباره عناوین کار گروهی",
        "instructor": "دکتر حصاری؛ دکتر یونس‌پور و دکتر صراف‌زاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 4,
        "sessionNumbers": [
          4
        ],
        "dates": [
          "1405/07/08"
        ],
        "title": "کار عملی انتخاب موضوع و بیان مسئله و نهایی‌کردن عنوان کار گروهی",
        "instructor": "دکتر یونس‌پور و دکتر صراف‌زاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": null,
        "sessionNumbers": [],
        "dates": [
          "1405/07/08"
        ],
        "title": "آشنایی با ساختار مقالات علمی",
        "instructor": "دکتر غلامی",
        "references": [],
        "sessionMode": "virtual",
        "sourcePage": 1,
        "sessionKey": "4-virtual",
        "sessionLabel": "محتوای تکمیلی جلسه ۴"
      },
      {
        "sessionNumber": 5,
        "sessionNumbers": [
          5
        ],
        "dates": [
          "1405/07/11"
        ],
        "title": "ارائه عناوین انتخابی و بیان مسئله",
        "instructor": "دکتر یونس‌پور و دکتر صراف‌زاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 6,
        "sessionNumbers": [
          6
        ],
        "dates": [
          "1405/07/15"
        ],
        "title": "اهداف، فرضیات و متغیرها؛ کار گروهی اهداف، فرضیات و متغیرها",
        "instructor": "دکتر یونس‌پور؛ دکتر یونس‌پور و دکتر صراف‌زاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 7,
        "sessionNumbers": [
          7
        ],
        "dates": [
          "1405/07/18"
        ],
        "title": "ملاحظات خاص انواع مطالعات (همگروهی، مورد-شاهدی و مداخله‌ای)",
        "instructor": "دکتر خرازی‌فرد",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 8,
        "sessionNumbers": [
          8
        ],
        "dates": [
          "1405/07/22"
        ],
        "title": "روش جمع‌آوری داده‌ها و پرسشنامه؛ کار گروهی انتخاب متغیرها",
        "instructor": "دکتر پاکدامن؛ دکتر یونس‌پور و دکتر صراف‌زاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 9,
        "sessionNumbers": [
          9
        ],
        "dates": [
          "1405/07/25"
        ],
        "title": "خطاهای تحقیق، تورش، مخدوش‌کنندگی و برهم‌کنش؛ کار گروهی نگارش روش اجرا",
        "instructor": "دکتر خرازی‌فرد؛ دکتر یونس‌پور و دکتر صراف‌زاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 2
      },
      {
        "sessionNumber": 10,
        "sessionNumbers": [
          10
        ],
        "dates": [
          "1405/07/29"
        ],
        "title": "آشنایی با روش‌های نمونه‌گیری و تعیین حجم نمونه؛ کار گروهی تعیین حجم نمونه",
        "instructor": "دکتر یونس‌پور؛ دکتر یونس‌پور و دکتر صراف‌زاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 2
      },
      {
        "sessionNumber": 11,
        "sessionNumbers": [
          11
        ],
        "dates": [
          "1405/07/29"
        ],
        "title": "اخلاق در پژوهش؛ مطالعات کیفی",
        "instructor": "دکتر سرگران؛ دکتر محبی",
        "references": [],
        "sessionMode": "virtual",
        "sourcePage": 2
      },
      {
        "sessionNumber": 12,
        "sessionNumbers": [
          12
        ],
        "dates": [
          "1405/08/02"
        ],
        "title": "مدیریت تحقیق؛ کار گروهی مدیریت تحقیق",
        "instructor": "دکتر صراف‌زاده؛ دکتر یونس‌پور و دکتر صراف‌زاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 2
      },
      {
        "sessionNumber": 13,
        "sessionNumbers": [
          13
        ],
        "dates": [
          "1405/08/06"
        ],
        "title": "کار گروهی نگارش روش اجرای پروپوزال",
        "instructor": "دکتر یونس‌پور و دکتر صراف‌زاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 2
      },
      {
        "sessionNumber": 14,
        "sessionNumbers": [
          14
        ],
        "dates": [
          "1405/08/09"
        ],
        "title": "ملاحظات خاص مطالعات ارزیابی تست‌های تشخیصی؛ آشنایی با شیوه تهیه پاورپوینت ارائه برای جلسه دفاع",
        "instructor": "دکتر خرازی‌فرد؛ دکتر یونس‌پور",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 2
      },
      {
        "sessionNumber": 15,
        "sessionNumbers": [
          15
        ],
        "dates": [
          "1405/08/13"
        ],
        "title": "ارائه کار گروهی (پروپوزال گروهی)",
        "instructor": "همه اساتید",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 2
      },
      {
        "sessionNumber": 16,
        "sessionNumbers": [
          16
        ],
        "dates": [
          "1405/08/16"
        ],
        "title": "ارائه کار گروهی (پروپوزال گروهی)",
        "instructor": "همه اساتید",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 2
      }
    ]
  },
  "endodontics-basics-2": {
    "version": "1405-1406-1.corrected.1",
    "eventSlugs": [
      "endodontics-basics-2"
    ],
    "courseTitle": "مبانی اندودانتیکس ۲",
    "sourceCourseTitle": "مبانی اندودانتیکس ۲",
    "sourceFile": "تقویم آموزشی و دمو واحد مبانی 2 نیمسال اول 06-1405.pdf",
    "courseCoordinator": "",
    "sessions": [
      {
        "sessionNumber": 1,
        "sessionNumbers": [
          1
        ],
        "dates": [
          "1405/06/29"
        ],
        "title": "جمع‌آوری مولرهای ماگزیال و مندیبل، رادیوگرافی، تأیید استاد و گروه‌بندی",
        "instructor": "",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "sessionDetails": "گروه‌بندی دانشجویان در این جلسه انجام می‌شود."
      },
      {
        "sessionNumber": 2,
        "sessionNumbers": [
          2
        ],
        "dates": [
          "1405/06/31"
        ],
        "title": "دمانستریشن آشنایی با آناتومی داخلی دندان‌های مولر مندیبل و تهیه حفره دسترسی",
        "instructor": "دکتر نوری",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 3,
        "sessionNumbers": [
          3
        ],
        "dates": [
          "1405/07/05"
        ],
        "title": "دمانستریشن آشنایی با آناتومی داخلی دندان‌های مولر ماگزیال و تهیه حفره دسترسی",
        "instructor": "دکتر بابا احمدی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 4,
        "sessionNumbers": [
          4
        ],
        "dates": [
          "1405/07/07"
        ],
        "title": "تمرین تهیه حفره دسترسی روی دندان‌های مولر مندیبل و ماگزیال",
        "instructor": "",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 5,
        "sessionNumbers": [
          5
        ],
        "dates": [
          "1405/07/12"
        ],
        "title": "کوییز ۱ + دمانستریشن تکنیک‌های آماده‌سازی کانال‌های خمیده و باریک",
        "instructor": "دکتر ملک پور",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 6,
        "sessionNumbers": [
          6
        ],
        "dates": [
          "1405/07/14"
        ],
        "title": "تمرین آماده‌سازی کانال‌های ریشه دندان‌های مولر",
        "instructor": "",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 7,
        "sessionNumbers": [
          7
        ],
        "dates": [
          "1405/07/19"
        ],
        "title": "کوییز ۲ + دمانستریشن حوادث حین درمان و روش‌های پیشگیری و مدیریت",
        "instructor": "دکتر مروی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 8,
        "sessionNumbers": [
          8
        ],
        "dates": [
          "1405/07/21"
        ],
        "title": "تمرین آماده‌سازی کانال‌های ریشه و آبچوریشن",
        "instructor": "",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 9,
        "sessionNumbers": [
          9
        ],
        "dates": [
          "1405/07/26"
        ],
        "title": "کوییز ۳ + دمانستریشن اپکس لوکیتورها",
        "instructor": "دکتر حمیدزاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 10,
        "sessionNumbers": [
          10
        ],
        "dates": [
          "1405/07/28"
        ],
        "title": "تمرین آماده‌سازی و آبچوریشن کانال‌های ریشه",
        "instructor": "",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 11,
        "sessionNumbers": [
          11
        ],
        "dates": [
          "1405/08/03"
        ],
        "title": "کوییز ۴ + دمانستریشن ایزولاسیون و تمرین رابردم روی دندان‌های قدامی و مولر دنتیک",
        "instructor": "دکتر اسدیان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "sessionDetails": "تمرین بستن رابردم روی دندان‌های قدامی و مولر دنتیک."
      },
      {
        "sessionNumber": 12,
        "sessionNumbers": [
          12
        ],
        "dates": [
          "1405/08/05"
        ],
        "title": "تمرین آماده‌سازی و آبچوریشن کانال‌های ریشه",
        "instructor": "",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 13,
        "sessionNumbers": [
          13
        ],
        "dates": [
          "1405/08/10"
        ],
        "title": "کوییز ۵ + دمانستریشن داروی داخل کانال و ترمیم‌های موقت",
        "instructor": "دکتر حمیدزاده",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 14,
        "sessionNumbers": [
          14
        ],
        "dates": [
          "1405/08/12"
        ],
        "title": "تمرین آماده‌سازی و آبچوریشن کانال‌های ریشه",
        "instructor": "",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      }
    ]
  },
  "oral-health-practical-2": {
    "version": "1405-1406-1.corrected.1",
    "rotationRelative": true,
    "rotationSourceAnchor": "1405/06/28",
    "sourceTiming": {
      "default": {"start": "09:00", "end": "12:00", "appliesToVirtual": true}
    },
    "eventSlugs": [
      "oral-health-practical-2"
    ],
    "courseTitle": "سلامت دهان عملی ۲",
    "sourceCourseTitle": "سلامت عملی ۲",
    "sourceFile": "سلامت دهان عملی ۲.pdf",
    "courseCoordinator": "دکتر محمدرضا خامی",
    "sessions": [
      {
        "sessionNumber": 1,
        "sessionNumbers": [
          1
        ],
        "dates": [
          "1405/06/28",
          "1405/06/30",
          "1405/07/01"
        ],
        "title": "جلسه توجیهی / کارگاه پیشگیری",
        "instructor": "دکتر سرگران / دکتر پاکدامن",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 2,
        "sessionNumbers": [
          2
        ],
        "dates": [
          "1405/07/04",
          "1405/07/06",
          "1405/07/08"
        ],
        "title": "کارگاه مشاوره تغذیه و کار عملی تغذیه",
        "instructor": "دکتر غلامی / دکتر رازقی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 3,
        "sessionNumbers": [
          3
        ],
        "dates": [
          "1405/07/11",
          "1405/07/13",
          "1405/07/15"
        ],
        "title": "کارگاه پیشگیری ۲",
        "instructor": "دکتر پاکدامن",
        "references": [],
        "sessionMode": "virtual",
        "sourcePage": 1
      },
      {
        "sessionNumber": 4,
        "sessionNumbers": [
          4
        ],
        "dates": [
          "1405/07/18",
          "1405/07/20",
          "1405/07/22"
        ],
        "title": "کارگاه شاخص‌های سلامت دهان و فلورایدتراپی",
        "instructor": "دکتر محبی / دکتر حصاری",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "sessionDetails": "کار عملی نیازسنجی و ثبت شاخص؛ دمونستریشن فلورایدتراپی"
      },
      {
        "sessionNumber": 5,
        "sessionNumbers": [
          5
        ],
        "dates": [
          "1405/07/25",
          "1405/07/27",
          "1405/07/29"
        ],
        "title": "فیلد ۱ — نیازسنجی و ثبت شاخص کودکان دبستانی",
        "instructor": "دکتر رازقی / دکتر غلامی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "sessionDetails": "معاینه و ارزیابی شاخص، ارزیابی خطر پوسیدگی، مشاوره تغذیه و فلورایدتراپی"
      },
      {
        "sessionNumber": 6,
        "sessionNumbers": [
          6
        ],
        "dates": [
          "1405/08/02",
          "1405/08/04",
          "1405/08/06"
        ],
        "title": "فیلد ۲ — نیازسنجی و ثبت شاخص کودکان دبستانی",
        "instructor": "دکتر پاکدامن / دکتر خامی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "sessionDetails": "معاینه و ارزیابی شاخص، ارزیابی خطر پوسیدگی، مشاوره تغذیه و فلورایدتراپی"
      },
      {
        "sessionNumber": 7,
        "sessionNumbers": [
          7
        ],
        "dates": [
          "1405/08/09",
          "1405/08/11",
          "1405/08/13"
        ],
        "title": "فیلد ۳ — نیازسنجی و ثبت شاخص کودکان دبستانی",
        "instructor": "دکتر سرگران / دکتر حصاری",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 2,
        "sessionDetails": "معاینه و ارزیابی شاخص، ارزیابی خطر پوسیدگی، مشاوره تغذیه و فلورایدتراپی"
      },
      {
        "sessionNumber": 8,
        "sessionNumbers": [
          8
        ],
        "dates": [
          "1405/08/16",
          "1405/08/18",
          "1405/08/20"
        ],
        "title": "ارائه کار گروهی کارگاه پیشگیری / تحویل گزارش نهایی فیلد",
        "instructor": "دکتر پاکدامن / دکتر رازقی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 2
      }
    ]
  },
  "oral-health-theory-2": {
    "version": "1405-1406-1.corrected.1",
    "sourceTiming": {
      "default": {"start": "07:30", "end": "08:30", "appliesToVirtual": true}
    },
    "eventSlugs": [
      "oral-health-theory-2"
    ],
    "courseTitle": "سلامت دهان نظری ۲",
    "sourceCourseTitle": "سلامت نظری ۲",
    "sourceFile": "سلامت دهان نظری ۲.pdf",
    "courseCoordinator": "دکتر محمدرضا خامی",
    "sessions": [
      {
        "sessionNumber": 1,
        "sessionNumbers": [
          1
        ],
        "dates": [
          "1405/06/31"
        ],
        "title": "اپیدمیولوژی",
        "instructor": "دکتر سمانه رازقی",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 2,
        "sessionNumbers": [
          2
        ],
        "dates": [
          "1405/07/07"
        ],
        "title": "دندانپزشکی مبتنی بر شواهد و ارزیابی نقادانه",
        "instructor": "دکتر رضا یزدانی",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 3,
        "sessionNumbers": [
          3
        ],
        "dates": [
          "1405/07/14"
        ],
        "title": "آموزش در ارتقاء سلامت دهان و دندان ۲",
        "instructor": "دکتر حسین حصاری",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 4,
        "sessionNumbers": [
          4
        ],
        "dates": [
          "1405/07/21"
        ],
        "title": "تغییرات رفتاری در ارتقاء سلامت",
        "instructor": "دکتر حسین حصاری",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 5,
        "sessionNumbers": [
          5
        ],
        "dates": [
          "1405/07/28"
        ],
        "title": "شاخص‌های سلامت دهان و دندان ۲",
        "instructor": "دکتر سیمین زهرا محبی",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 6,
        "sessionNumbers": [
          6
        ],
        "dates": [
          "1405/07/28"
        ],
        "title": "سلامت و پیشگیری از بیماری‌های نسوج سخت دندانی ۲",
        "instructor": "دکتر سیمین زهرا محبی",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "offline",
        "sourcePage": 1
      },
      {
        "sessionNumber": 7,
        "sessionNumbers": [
          7
        ],
        "dates": [
          "1405/08/05"
        ],
        "title": "ارزیابی خطر پوسیدگی",
        "instructor": "دکتر افسانه پاکدامن",
        "references": [
          "اسلایدهای مدرس"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 8,
        "sessionNumbers": [
          8
        ],
        "dates": [
          "1405/08/12"
        ],
        "title": "درمان‌های محافظه‌کارانه",
        "instructor": "دکتر افسانه پاکدامن",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 9,
        "sessionNumbers": [
          9
        ],
        "dates": [
          "1405/08/19"
        ],
        "title": "پیشگیری از صدمات تروماتیک دندانی",
        "instructor": "دکتر سمانه رازقی",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 10,
        "sessionNumbers": [
          10
        ],
        "dates": [
          "1405/08/19"
        ],
        "title": "سلامت دهان بیماران خاص و سالمندان",
        "instructor": "دکتر کتایون سرگران",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "offline",
        "sourcePage": 1
      },
      {
        "sessionNumber": 11,
        "sessionNumbers": [
          11
        ],
        "dates": [
          "1405/08/26"
        ],
        "title": "دخانیات و سلامت دهان",
        "instructor": "دکتر محمدرضا خامی",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 12,
        "sessionNumbers": [
          12
        ],
        "dates": [
          "1405/09/03"
        ],
        "title": "نظام سلامت",
        "instructor": "دکتر کتایون سرگران",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 13,
        "sessionNumbers": [
          13
        ],
        "dates": [
          "1405/09/10"
        ],
        "title": "نظام سلامت در ایران",
        "instructor": "دکتر رضا یزدانی",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "in_person",
        "sourcePage": 2
      },
      {
        "sessionNumber": 14,
        "sessionNumbers": [
          14
        ],
        "dates": [
          "1405/09/10"
        ],
        "title": "جامعه‌شناسی سلامت",
        "instructor": "دکتر مهدیا غلامی",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "offline",
        "sourcePage": 2
      },
      {
        "sessionNumber": 15,
        "sessionNumbers": [
          15
        ],
        "dates": [
          "1405/09/17"
        ],
        "title": "اقتصاد سلامت",
        "instructor": "دکتر مهدیا غلامی",
        "references": [
          "اسلایدهای مدرس"
        ],
        "sessionMode": "in_person",
        "sourcePage": 2
      },
      {
        "sessionNumber": 16,
        "sessionNumbers": [
          16
        ],
        "dates": [
          "1405/09/17"
        ],
        "title": "مدیریت سلامت و برنامه‌ریزی برای ارتقاء سلامت",
        "instructor": "دکتر محمدرضا خامی",
        "references": [
          "اسلایدهای مدرس",
          "رفرنس مکمل: کتاب ملی"
        ],
        "sessionMode": "offline",
        "sourcePage": 2
      }
    ]
  },
  "periodontology-theory-1": {
    "version": "1405-1406-1.corrected.1",
    "sourceTiming": {
      "default": {"start": "07:30", "end": "08:30", "appliesToVirtual": true}
    },
    "eventSlugs": [
      "periodontology-theory-1"
    ],
    "courseTitle": "پریو نظری ۱",
    "sourceCourseTitle": "پریو نظری ۱",
    "sourceFile": "پریودنتولوژی نظری ۱.pdf",
    "sessions": [
      {
        "sessionNumber": 1,
        "sessionNumbers": [
          1
        ],
        "dates": [
          "1405/06/28"
        ],
        "title": "آناتومی انساج پریودنتال ۱",
        "instructor": "دکتر همتیان",
        "references": [],
        "sessionMode": "virtual",
        "sourcePage": 1
      },
      {
        "sessionNumber": 2,
        "sessionNumbers": [
          2
        ],
        "dates": [
          "1405/07/04"
        ],
        "title": "آناتومی انساج پریودنتال ۲",
        "instructor": "دکتر همتیان",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 3,
        "sessionNumbers": [
          3
        ],
        "dates": [
          "1405/07/11"
        ],
        "title": "پلاک میکروبی و نقش کلکلوس و سایر عوامل موضعی",
        "instructor": "دکتر دولت‌آبادی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 4,
        "sessionNumbers": [
          4
        ],
        "dates": [
          "1405/07/18"
        ],
        "title": "میکروبیولوژی پریودنتال",
        "instructor": "دکتر کدخدا",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 5,
        "sessionNumbers": [
          5
        ],
        "dates": [
          "1405/07/25"
        ],
        "title": "علائم و نشانه‌های بیماری‌های پریودنتال",
        "instructor": "دکتر اکبری",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 6,
        "sessionNumbers": [
          6
        ],
        "dates": [
          "1405/08/02"
        ],
        "title": "پاتوژنز پریودنتال",
        "instructor": "دکتر یعقوبی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 7,
        "sessionNumbers": [
          7
        ],
        "dates": [
          "1405/08/02"
        ],
        "title": "پاکت‌های پریودنتال و الگوی تخریب استخوان",
        "instructor": "دکتر مسلمی",
        "references": [],
        "sessionMode": "virtual",
        "sourcePage": 1
      },
      {
        "sessionNumber": 8,
        "sessionNumbers": [
          8
        ],
        "dates": [
          "1405/08/09"
        ],
        "title": "روش‌های مکانیکی کنترل پلاک",
        "instructor": "دکتر خورسند",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 9,
        "sessionNumbers": [
          9
        ],
        "dates": [
          "1405/08/16"
        ],
        "title": "روش‌های شیمیایی کنترل پلاک",
        "instructor": "دکتر حیدری",
        "references": [],
        "sessionMode": "virtual",
        "sourcePage": 1
      },
      {
        "sessionNumber": 10,
        "sessionNumbers": [
          10
        ],
        "dates": [
          "1405/08/23"
        ],
        "title": "ترامای اکلوزالی و ارتباط آن با بیماری‌های پریودنتال",
        "instructor": "دکتر مهدی‌پور",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 11,
        "sessionNumbers": [
          11
        ],
        "dates": [
          "1405/08/30"
        ],
        "title": "ارتباط بیماری‌های سیستمیک با بیماری‌های پریودنتال ۱ (ایدز، هپاتیت، اختلالات هماتولوژیک و نقص ایمنی)",
        "instructor": "دکتر هوشیار",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 12,
        "sessionNumbers": [
          12
        ],
        "dates": [
          "1405/09/07"
        ],
        "title": "ارتباط بیماری‌های سیستمیک با بیماری‌های پریودنتال ۲ (دیابت، هورمونال)",
        "instructor": "دکتر هوشیار",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 13,
        "sessionNumbers": [
          13
        ],
        "dates": [
          "1405/09/14"
        ],
        "title": "ارزیابی خطر در بیماری‌های پریودنتال",
        "instructor": "دکتر حیدری",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 14,
        "sessionNumbers": [
          14
        ],
        "dates": [
          "1405/09/21"
        ],
        "title": "بوی بد دهان و علل و درمان آن",
        "instructor": "دکتر روستا",
        "references": [],
        "sessionMode": "virtual",
        "sourcePage": 1
      },
      {
        "sessionNumber": 15,
        "sessionNumbers": [
          15
        ],
        "dates": [
          "1405/09/21"
        ],
        "title": "اپیدمیولوژی بیماری‌های پریودنتال",
        "instructor": "دکتر راعی",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 16,
        "sessionNumbers": [
          16
        ],
        "dates": [
          "1405/09/28"
        ],
        "title": "آزمون بورد",
        "instructor": "",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1
      },
      {
        "sessionNumber": 17,
        "sessionNumbers": [
          17
        ],
        "dates": [
          "1405/10/05"
        ],
        "title": "اطلس بیماری‌های پریودنتال",
        "instructor": "دکتر اکبری",
        "references": [],
        "sessionMode": "in_person",
        "sourcePage": 1,
        "sourceTitle": "Atlas of periodontal diseases"
      }
    ]
  }
}
JSON, true, 512, JSON_THROW_ON_ERROR);
    return is_array($catalog) ? $catalog : [];
}
