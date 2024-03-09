<?php

$i18n = [
    // 注册页面
    "create_account"  => "请创建帐号后登录",
    'username'        => "用户名",
    'password'        => "密码",
    "email"           => "电子邮箱",
    "confirm_password" => "确认密码",
    "main_currency"   => "主要货币",
    "language"        => "语言",
    "passwords_dont_match" => "密码不匹配",
    "registration_failed" => "注册失败，请重试。",
    "register"        => "注册",

    // 登录页面
    'please_login'    => "请登录",
    'stay_logged_in'  => "30 天内免登录",
    'login'           => "登录",
    'login_failed'    => "登录信息错误",

    // 页眉
    'subscriptions'   => "订阅",
    'stats'           => "统计",
    'settings'        => "设置",
    'about'           => "关于",
    'logout'          => "登出",

    // 订阅页面
    "subscription"    => "订阅",
    "no_subscriptions_yet" => "您还没有任何订阅",
    "add_first_subscription" => "添加首个订阅",
    'new_subscription' => "新订阅",
    'search'          => "搜索",
    'sort'            => "排序",
    'name'            => "名称",
    'last_added'      => "创建时间",
    'price'           => "价格",
    'next_payment'    => "下次支付时间",
    'inactive'        => "停用订阅",
    'member'          => "成员",
    'category'        => "分类",
    'payment_method'  => "支付方式",
    "Daily"           => "每日",
    "Weekly"          => "每周",
    "Monthly"         => "每月",
    "Yearly"          => "每年",
    "days"            => "天",
    "weeks"           => "周",
    "months"          => "月",
    "years"           => "年",
    "external_url"    => "访问外部链接",
    "empty_page"      => "空白页面",
    "clear_filters"   => "清除筛选",
    "no_matching_subscriptions" => "没有匹配的订阅",
    
    // 订阅表单
    "add_subscription" => "添加订阅",
    "edit_subscription" => "编辑订阅",
    "subscription_name" => "订阅名称",
    "logo_preview"    => "Logo 预览",
    "search_logo"     => "在网上搜索 Logo",
    "web_search"      => "网页搜索",
    "currency"        => "货币",
    "billing_cycle"   => "账单周期",
    "frequency"       => "频率",
    "cycle"           => "周期",
    "next_payment"    => "下次支付",
    "payment_method"  => "支付方式",
    "no_category"     => "无分类",
    "paid_by"         => "付款人",
    "url"             => "链接",
    "notes"           => "备注",
    "enable_notifications" => "为此订阅启用通知",
    "delete"          => "删除",
    "cancel"          => "取消",
    "upload_logo"     => "上传 Logo",
    
    // 统计页面
    'general_statistics' => "总体统计",
    'active_subscriptions' => "活跃订阅",
    'inactive_subscriptions' => "非活动订阅",
    'monthly_cost'    => "月费用",
    'yearly_cost'     => "年费用",
    'average_monthly' => "平均每月订阅费用",
    'most_expensive'  => "最昂贵订阅费用",
    'amount_due'      => "本月应付金额",
    'monthly_savings' => "每月节省",
    'split_views'     => "拆分视图",
    'category_split'  => "分类视图",
    'household_split' => "家庭视图",
    'payment_method_split' => "支付方式视图",
    
    // 关于页面
    'about_and_credits' => "关于和鸣谢",
    'license'         => "许可证",
    'issues_and_requests' => "问题反馈与功能请求",
    'the_author'      => "作者",
    'icons'           => "图标",
    'payment_icons'   => "支付图标",
    
    // 设置页面
    'user_details'    => "用户详情",
    "household"       => "家庭",
    "save_member"     => "保存成员",
    "delete_member"   => "删除成员",
    "cant_delete_member" => "不能删除主要成员",
    "cant_delete_member_in_use" => "不能删除拥有订阅的成员",
    "household_info"  => "电子邮件字段允许通知家庭成员订阅即将过期。",
    "notifications"   => "通知",
    "enable_email_notifications" => "启用电子邮件通知",
    "notify_me"       => "通知提前时间",
    "day_before"      => "天", // 设置标题（`notify_me`）中已经表明是提前多少天，因此这里直接用单位即可
    "days_before"     => "天",
    "smtp_address"    => "SMTP 地址",
    "port"            => "端口",
    "smtp_username"   => "SMTP 用户名",
    "smtp_password"   => "SMTP 密码",
    "from_email"      => "发件人邮箱（可选）",
    "smtp_info"       => "SMTP 密码以明文传输和存储。为安全起见，建议专门为 Wallos 创建一个账户。",
    "categories"      => "分类",
    "save_category"   => "保存分类",
    "delete_category" => "删除分类",
    "cant_delete_category_in_use" => "不能删除正在订阅中的分类",
    "currencies"      => "货币",
    "save_currency"   => "保存货币",
    "delete_currency" => "删除货币",
    "cant_delete_main_currency" => "不能删除主要货币",
    "cant_delete_currency_in_use" => "不能删除正在使用中的货币",
    "exchange_update" => "汇率最后更新于",
    "currency_info"   => "如要查找支持的货币与对应代码，请前往",
    "currency_performance" => "为提高性能，建议您只保留常用货币。",
    "fixer_api_key"   => "Fixer API 密钥",
    "api_key"         => "API 密钥",
    "provider"        => "提供商",
    "fixer_info"      => "如果您使用多种货币，希望统计信息和订阅排序更精确，则需要 Fixer API 密钥来查询汇率（可免费申请）。",
    "get_key"         => "申请密钥",
    "get_free_fixer_api_key" => "申请免费 Fixer API 密钥",
    "get_key_alternative" => "或者，您也可以从以下网站获取免费的修复程序 api 密钥",
    "display_settings" => "显示设置",
    "switch_theme"    => "切换浅色/深色主题",
    "calculate_monthly_price" => "计算并显示所有订阅的月价格",
    "convert_prices"  => "始终按我的主要货币转换和显示价格（较慢）",
    "experimental_settings" => "实验性设置",
    "remove_background" => "尝试从图片搜索中移除标志的背景（实验性）",
    "experimental_info" => "实验性设置，可能存在问题。",
    "payment_methods" => "支付方式",
    "payment_methods_info" => "点击支付方式以禁用/启用。",
    "rename_payment_methods_info" => "点击付款方式名称，重新命名该付款方式。",
    "cant_delete_payment_method_in_use" => "不能禁用正在使用的支付方式",
    "add_custom_payment" => "添加自定义支付方式",
    "payment_method_name" => "支付方式名称",
    "payment_method_added_successfuly" => "支付方式已成功添加",
    "payment_method_removed" => "支付方式已移除",
    "disable"         => "禁用",
    "enable"          => "启用",
    "rename_payment_method" => "重命名支付方式",
    "payment_renamed" => "支付方式已重命名",
    "payment_not_renamed" => "支付方式未重命名",
    "test"            => "测试",
    "add"             => "添加",
    "save"            => "保存",
    "export_subscriptions" => "导出订阅",
    "export_to_json"  => "导出为 JSON",

    // Filters menu
    "filter"          => "筛选",
    "clear"           => "清除",
    
    // Toast
    "success"         => "成功",
    
    // Endpoint responses
    "session_expired" => "您的会话已过期，请重新登录",
    "fields_missing" => "部分字段未填写",
    "fill_all_fields" => "请填写所有字段",
    "fill_mandatory_fields" => "请填写所有必填字段",
    "error" => "错误",
    
    // Category
    "failed_add_category" => "添加分类失败",
    "failed_edit_category" => "编辑分类失败",
    "category_in_use" => "分类正在被订阅使用中，无法移除",
    "failed_remove_category" => "移除分类失败",
    "category_saved" => "分类已保存",
    "category_removed" => "分类已移除",
    "sort_order_saved" => "排序顺序已保存",
    
    // Currency
    "currency_saved" => "货币已保存。",
    "error_adding_currency" => "添加货币时出错。",
    "failed_to_store_currency" => "存储货币到数据库失败。",
    "currency_in_use" => "货币正在被订阅使用中，无法删除。",
    "currency_is_main" => "货币已被设置为主货币，无法删除。",
    "failed_to_remove_currency" => "从数据库删除货币失败。",
    "failed_to_store_api_key" => "存储 API 密钥到数据库失败。",
    "invalid_api_key" => "API 密钥无效。",
    "api_key_saved" => "API 密钥已成功保存",
    "currency_removed" => "货币已移除",
    
    // Household
    "failed_add_household" => "添加家庭成员失败",
    "failed_edit_household" => "编辑家庭成员失败",
    "failed_remove_household" => "移除家庭成员失败",
    "household_in_use" => "此成员有相关的订阅，无法移除",
    "member_saved" => "成员已保存",
    "member_removed" => "成员已移除",
    
    // Notifications
    "error_saving_notifications" => "保存通知数据时出错。",
    "wallos_notification" => "Wallos 通知",
    "test_notification" => "这是一条测试通知。如果您看到此消息，说明 Wallos 通知邮件配置正确。",
    "email_error" => "发送电子邮件时出错",
    "notification_sent_successfuly" => "通知已成功发送",
    "notifications_settings_saved" => "通知设置已成功保存。",
    
    // Payments
    "payment_in_use" => "无法禁用正在使用的支付方式",
    "failed_update_payment" => "更新数据库中的支付方式失败",
    "enabled" => "已启用",
    "disabled" => "已禁用",
    
    // Subscription
    "error_fetching_image" => "获取图片时出错",
    "subscription_updated_successfuly" => "订阅已成功更新",
    "subscription_added_successfuly" => "订阅已成功添加",
    "error_deleting_subscription" => "删除订阅时出错。",
    "invalid_request_method" => "请求方法无效。",
    
    // User
    "error_updating_user_data" => "更新用户数据时出错。",
    "user_details_saved" => "用户详细信息已保存",

];

?>