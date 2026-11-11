<?php
$tabs = [
    [
        "label" => T("Profile", "Overview"),
        "href" => "/profile",
        "active" => (int) $vars["selectedTab"] === 1,
    ],
];

if (!$vars["banned"]) {
    $tabs[] = [
        "label" => T("Profile", "Edit Profile"),
        "href" => "/profile/edit",
        "active" => (int) $vars["selectedTab"] === 2,
    ];
}
echo \resources\View\PHPBatchView::render("layout/tabStrip", [
    "tabs" => $tabs,
    "favor" => true,
    "wrapperClass" => "tabFavorWrapper",
    "ids" => false,
    "reactState" => true,
    "bareAnchors" => true,
]);
?>
<script type="text/javascript">
jQuery(function () {
    var $tabs = jQuery('#playerProfile .contentNavi.subNavi .tabItem');
    $tabs.eq(0).on('click', function () { window.location.href = '/profile/overview'; });
    $tabs.eq(1).on('click', function () { window.location.href = '/profile/edit'; });
});
</script>
