import unittest
from types import SimpleNamespace
from unittest.mock import patch
from dent_bot.classops_shell import canonical_home_screen, hamiad_overview_screen, hamiad_courses_screen, hamiad_course_screen, hamiad_tasks_screen, hamiad_assignment_screen
from dent_bot.classops_ui import handle_classops_ui_callback

def actions(screen):
    return [b.get('callback_data', '') for row in screen.keyboard['inline_keyboard'] for b in row]

class Site:
    def __init__(self): self.calls = []
    def request(self, action, uid, **fields):
        self.calls.append(action)
        if action == 'hamiadCourseV1':
            return {'detail': {'course': {'id':9082,'title':'آموزش نظری'}, 'items': []}}
        return {'dashboard': {'connected': True, 'courses':[{'id':9082,'title':'آموزش نظری','resourceCount':1,'assignmentCount':0}], 'resources':1,'assignments':[]}}

class TestHamiad(unittest.TestCase):
    def test_main_and_pagination(self):
        self.assertIn('v1:hamiad-center', actions(canonical_home_screen(is_owner=True)))
        data={'connected':True,'courses':[{'id':8000+n,'title':'درس '+str(n),'resourceCount':2,'assignmentCount':0} for n in range(12)],'resources':24,'assignments':[]}
        self.assertIn('ترم ۷',str(hamiad_overview_screen(data,site_url='https://dentistry1402tums.ir').text))
        self.assertIn('v1:hamiad:courses:1',actions(hamiad_courses_screen(data,page=0)))
        self.assertIn('v1:hamiad:courses:0',actions(hamiad_courses_screen(data,page=1)))
        self.assertIn('هیچ تکلیفی',str(hamiad_tasks_screen(data).text))
        self.assertIn('هنوز فایل',str(hamiad_course_screen({'course':data['courses'][0],'items':[]}).text))

    def test_future_closed_task_is_not_past_due_in_bot(self):
        task = {
            'id': 45, 'courseId': 9082, 'title': 'تکلیف روش تحقیق',
            'courseTitle': 'روش تحقیق ۲', 'opensText': '۱۴۰۵/۹/۱ ساعت ۰۰:۰۰',
            'deadlineText': '۱۴۰۵/۹/۲۵ ساعت ۲۳:۵۹',
            'submissionStatus': 'CLOSED', 'submissionWindowStatus': 'not_open',
        }
        screen = hamiad_tasks_screen({'assignments': [task]})
        content = str(screen.text)
        self.assertIn('شروع ارسال', content)
        self.assertIn('آخرین مهلت ارسال', content)
        self.assertIn('هنوز شروع نشده', content)
        self.assertNotIn('CLOSED', content)
        detail = str(hamiad_assignment_screen(task).text)
        self.assertIn('آخرین مهلت ارسال', detail)
        self.assertNotIn('CLOSED', detail)

    @patch('dent_bot.classops_ui._show')
    def test_callbacks_owner_and_nonowner(self, show):
        site=Site()
        app=SimpleNamespace(owner_id=7,site_api=site,site_url='https://dentistry1402tums.ir',api=SimpleNamespace(answer_callback=lambda x:None))
        def cb(data,uid=7): return {'id':'callback','data':'v1:'+data,'from':{'id':uid},'message':{'chat':{'id':uid,'type':'private'},'message_id':9}}
        for name,action in [('hamiad-center','hamiadDashboardV1'),('hamiad:courses:0','hamiadDashboardV1'),('hamiad:tasks:0','hamiadDashboardV1'),('hamiad:course:9082','hamiadCourseV1')]:
            self.assertTrue(handle_classops_ui_callback(app,cb(name)))
            self.assertEqual(site.calls[-1],action)
        before=len(site.calls)
        self.assertTrue(handle_classops_ui_callback(app,cb('hamiad-center',uid=8)))
        self.assertEqual(len(site.calls),before)

if __name__=='__main__': unittest.main()
