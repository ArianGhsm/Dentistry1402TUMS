#!/usr/bin/env python3
"""Reproduce the owner-provided 24-question orthodontics midterm transcript."""
import argparse
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / 'scripts/data/orthodontics_midterm_24_source.txt'
OUTPUT = ROOT / 'public_html/api/exams_orthodontics_midterm_sample_questions.php'
DIGITS = str.maketrans('۰۱۲۳۴۵۶۷۸۹', '0123456789')
KEY = [2,1,4,4,1,4,2,3,3,3,4,2,4,1,4,3,2,1,1,1,1,1,3,3]
TOPICS = ['ترمینولوژی و اکلوژن']*3 + ['رشد و تکامل صورت']*8 + ['روش‌های اندازه‌گیری رشد'] + ['تکامل اکلوژن']*6 + ['آنالیزهای قالب دندانی']*6

def number(s):
    return int(s.translate(DIGITS))

def field(block, label, optional=False):
    m = re.search(r'^'+re.escape(label)+r'\s*(.+)$', block, re.M)
    if not m and not optional:
        raise ValueError('Missing '+label)
    return m.group(1).strip() if m else ''

def build(source):
    result = []
    for block in re.split(r'(?m)^═+\s*$', source):
        block = block.strip()
        stem = re.match(r'^([۰-۹]+)\.\s*(.+)$', block, re.M)
        if not stem or stem.start() != 0:
            continue
        n = number(stem[1])
        assert n == len(result)+1
        pre, sep, post = block.partition('پاسخ تشریحی و تحلیل گزینه‌ها:')
        analysis, sep2, review = post.partition('مرور آموزشی مبحث:')
        assert sep and sep2 and review.strip()
        opts = re.findall(r'^([۱-۴])\)\s*(.+)$', pre, re.M)
        rats = re.findall(r'^([۱-۴])\)\s*(پاسخ منتخب|غیرمنتخب) — (.+)$', analysis, re.M)
        assert [number(x[0]) for x in opts] == [1,2,3,4]
        assert [number(x[0]) for x in rats] == [1,2,3,4]
        answer = field(pre,'پاسخ مستقل:')
        scan = field(pre,'گزینه علامت زده شده در فایل اسکن شده نمونه سوالات:')
        status = field(pre,'نتیجه تطبیق:')
        reference = field(pre,'منبع:')
        note = field(pre,'توضیح وضعیت سؤال:',True)
        idx = number(re.match(r'گزینه ([۱-۴])',answer)[1])-1
        scan_idx = number(re.match(r'گزینه ([۱-۴])',scan)[1])-1
        assert idx == KEY[n-1]-1
        assert [i for i,x in enumerate(rats) if x[1]=='پاسخ منتخب']==[idx]
        assert (idx!=scan_idx)==(n==5)
        assert answer.split(' — ',1)[1] == opts[idx][1]
        assert scan.split(' — ',1)[1] == opts[scan_idx][1]
        sections=[
            {'label':'علت درستی پاسخ مستقل','value':rats[idx][2],'tone':'success'},
            {'label':'مرور آموزشی','value':review.strip(),'tone':'info'},
        ]
        if note:
            sections.append({'label':'توضیح وضعیت سؤال','value':note,'tone':'warning'})
        result.append({'number':n,'topic':TOPICS[n-1],'question':stem[2],
            'options':[x[1] for x in opts],'correctIndex':idx,
            'answerMeta':[
                {'label':'گزینهٔ علامت‌خورده در اسکن','value':scan,'tone':'info'},
                {'label':'پاسخ بررسی مستقل','value':answer,'tone':'success'},
                {'label':'تطبیق با اسکن','value':status,'tone':'warning' if idx!=scan_idx else 'info'},
                {'label':'منبع و استناد','value':reference,'tone':'info','wide':True},
            ],'answerSections':sections,'optionRationales':[x[2] for x in rats],
            'explanation':rats[idx][2],'reference':reference})
    assert len(result)==24
    return result

if __name__=='__main__':
    parser=argparse.ArgumentParser()
    parser.add_argument('--check',action='store_true')
    args=parser.parse_args()
    data=build(SOURCE.read_text(encoding='utf-8-sig'))
    out="<?php\ndeclare(strict_types=1);\n\n// Generated from the owner-provided transcript. Do not edit directly.\nfunction dent_exams_orthodontics_midterm_sample_source(): array\n{\n    return json_decode(<<<'QUESTION_JSON'\n"+json.dumps(data,ensure_ascii=False,indent=2)+"\nQUESTION_JSON, true, 512, JSON_THROW_ON_ERROR);\n}\n"
    if args.check:
        assert OUTPUT.read_text(encoding='utf-8')==out
    else:
        OUTPUT.parent.mkdir(parents=True,exist_ok=True)
        OUTPUT.write_text(out,encoding='utf-8')
    print('PASS: 24 questions, 96 options and rationales, 24 references and reviews, scan difference Q5')
