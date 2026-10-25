<?php
require_once WALLOS_ROOT . '/includes/variable_pricing.php';
wallos_test('one-off monthly amount returns to the base price in missing months', function () {
    $sub = ['id'=>1,'price'=>10,'has_variable_price'=>1,'price_history_fallback'=>'base'];
    $history = [1=>['2026-10'=>15,'2026-12'=>0]];
    assert_same(15.0, effective_subscription_price($sub,'2026-10',$history), 'recorded month wins');
    assert_same(10.0, effective_subscription_price($sub,'2026-11',$history), 'next month returns to base');
    assert_same(0.0, effective_subscription_price($sub,'2026-12',$history), 'zero amount still wins');
    assert_same(10.0, effective_subscription_price($sub,'2027-01',$history), 'zero does not carry forward');
    assert_same(10.0, effective_subscription_price($sub,'2026-09',$history), 'future entries never affect past');
    $sub['price_history_fallback']='previous';
    assert_same(15.0, effective_subscription_price($sub,'2026-11',$history), 'legacy continuation still available');
    unset($sub['price_history_fallback']);
    assert_same(15.0, effective_subscription_price($sub,'2026-11',$history), 'missing setting preserves legacy behavior');
    $sub['has_variable_price']=0;
    assert_same(10.0, effective_subscription_price($sub,'2026-10',$history), 'disabled history retains fixed behavior');
});
wallos_test('price fallback validates exact modes and migration defaults to previous', function () {
    assert_same('base',validate_price_history_fallback('base'),'base valid');
    assert_same('previous',validate_price_history_fallback('previous'),'previous valid');
    foreach (['', 'actual', null, []] as $value) {
        try { validate_price_history_fallback($value); assert_true(false,'reject invalid mode'); }
        catch (InvalidArgumentException $e) { assert_same('price_history_fallback_invalid',$e->getMessage(),'clear invalid mode'); }
    }
    $db = new SQLite3(':memory:'); $db->exec('CREATE TABLE subscriptions (id INTEGER PRIMARY KEY); INSERT INTO subscriptions VALUES (1)');
    require WALLOS_ROOT.'/migrations/000064.php'; require WALLOS_ROOT.'/migrations/000064.php';
    assert_same('previous',$db->querySingle('SELECT price_history_fallback FROM subscriptions WHERE id=1'),'legacy row unchanged'); $db->close();
});
wallos_test('one-off fallback flows through budget and upcoming prices', function () {
    require_once WALLOS_ROOT . '/includes/budget_period_calculations.php';
    require_once WALLOS_ROOT . '/includes/upcoming_payments.php';
    $db=wallos_test_open_database(); wallos_test_create_user($db,1,'alice');
    $next=date('Y-m-d',strtotime('+1 month')); $previous=date('Y-m',strtotime('-1 month',strtotime($next)));
    $db->exec("INSERT INTO subscriptions(id,user_id,name,price,currency_id,next_payment,cycle,frequency,auto_renew,inactive,has_variable_price,price_history_fallback) VALUES(1,1,'mobile',10,9010,'$next',3,1,1,0,1,'base')");
    $db->exec("INSERT INTO subscription_price_history(subscription_id,period,price) VALUES(1,'$previous',15)");
    $index=load_price_history_index($db,1); $rows=get_upcoming_payments($db,1,3);
    assert_same('base',$rows[0]['price_history_fallback'],'dashboard loads fallback');
    assert_same(10.0,effective_subscription_price($rows[0],substr($next,0,7),$index),'dashboard returns base');
    $sub=$db->querySingle('SELECT * FROM subscriptions WHERE id=1',true);
    assert_equals(10,computeAmountNeededInPeriod([$sub],new DateTime($next),new DateTime($next),$db,1,$index),'budget returns base');
    $db->close();
});
wallos_test('calendar price marker identifies explicit deviations only', function () {
    $sub=['id'=>1,'price'=>10,'has_variable_price'=>1]; $index=[1=>['2026-10'=>15,'2026-12'=>0,'2027-01'=>10]];
    assert_true(subscription_has_monthly_price_override($sub,'2026-10',$index),'extra package is marked');
    assert_true(subscription_has_monthly_price_override($sub,'2026-12',$index),'explicit zero differs from base');
    assert_true(!subscription_has_monthly_price_override($sub,'2026-11',$index),'carry-forward is not a recorded adjustment');
    assert_true(!subscription_has_monthly_price_override($sub,'2027-01',$index),'base amount not marked as different');
    $sub['has_variable_price']=0;
    assert_true(!subscription_has_monthly_price_override($sub,'2026-10',$index),'disabled history not marked');
});
