from dent_bot.app import DentBotApp, home, section
from dent_bot.app_shell_screens import home as shell_home, section as shell_section
from dent_bot.dialog_app_workflows import DialogAppWorkflows
from dent_bot.dynamic_screen_workflows import DynamicScreenWorkflows
from dent_bot.payment_app_workflows import PaymentAppWorkflows


def test_app_composes_existing_and_new_workflow_mixins():
    assert issubclass(DentBotApp, PaymentAppWorkflows)
    assert issubclass(DentBotApp, DialogAppWorkflows)
    assert issubclass(DentBotApp, DynamicScreenWorkflows)


def test_dialog_workflows_are_not_redeclared_in_app_shell():
    for name in ["_handle_onboarding_message", "_handle_dialog_message", "_handle_booklet_dialog"]:
        assert name not in DentBotApp.__dict__
        assert name in DialogAppWorkflows.__dict__


def test_dynamic_screen_core_is_owned_by_routing_mixin():
    assert "_dynamic_screen_core" not in DentBotApp.__dict__
    assert "_dynamic_screen_core" in DynamicScreenWorkflows.__dict__


def test_app_preserves_home_and_section_import_compatibility():
    assert home is shell_home
    assert section is shell_section
