<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
// Instantiate the permissions class
$perm = new Permission();

$ppic = (isset($_SESSION["ppic"]) && !empty($_SESSION["ppic"])) ? $_SESSION["ppic"] : "https://nostr.build/assets/temp_ppic.png";

$svg_logo = <<<SVG
<svg width="152" height="41" version="1.1" id="prefix__Layer_1" xmlns="http://www.w3.org/2000/svg" x="0" y="0" xml:space="preserve"><style>.prefix__st12{fill:#fff}</style><linearGradient id="prefix__SVGID_1_" gradientUnits="userSpaceOnUse" x1="38.3" y1="816.7" x2="153" y2="800.4" gradientTransform="matrix(1 0 0 -1 0 836.6)"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".5"/></linearGradient><path d="M42.9 16.6q1.3-1.7 3.4-1.7 1.1 0 2 .4.8.3 1.4 1t.9 1.7l.2 2.2v6h-3.1v-5.8l-.1-1.1-.4-1q-.3-.4-.8-.6l-1-.2q-.9 0-1.3.4l-.8.7-.4 1v6.6h-3.2v-11h3z" fill="url(#prefix__SVGID_1_)"/><linearGradient id="prefix__SVGID_00000137134570809090147310000010954992555997200264_" gradientUnits="userSpaceOnUse" x1="38.6" y1="818.8" x2="153.7" y2="802.4" gradientTransform="matrix(1 0 0 -1 0 836.6)"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".5"/></linearGradient><path d="M58.6 26.4q-2.8 0-4.3-1.5-1.6-1.5-1.6-4.2 0-1.4.4-2.4t1.2-1.8 1.8-1.2q1.1-.4 2.5-.4t2.4.4q1.2.4 1.9 1.2a5 5 0 0 1 1.1 1.8q.5 1 .5 2.3 0 2.8-1.6 4.3-1.5 1.5-4.3 1.5m0-8.8q-1.5 0-2.1.9c-.6.9-.7 1.3-.7 2.2q0 1.3.7 2.2t2 .9 2.2-.9.6-2.2-.6-2.2q-.8-1-2.1-1" fill="url(#prefix__SVGID_00000137134570809090147310000010954992555997200264_)"/><linearGradient id="prefix__SVGID_00000097464930856146120360000017786935914121585028_" gradientUnits="userSpaceOnUse" x1="38.9" y1="820.5" x2="153.8" y2="804.1" gradientTransform="matrix(1 0 0 -1 0 836.6)"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".5"/></linearGradient><path d="M70.7 17.3h-.6l-.5.2q-.3 0-.4.3l-.2.5q0 .3.3.5l.8.4 1 .2 1.3.3 1 .3q.7 0 1 .4.6.3 1 1 .4.5.3 1.4 0 .9-.3 1.5t-.7 1.1a4 4 0 0 1-1.7.8 9 9 0 0 1-4 0q-1-.2-1.8-.8l-1-1.1-.4-1.6H69q0 .7.7 1t1.4.3h.5q.3 0 .5-.2.3 0 .4-.3l.2-.4q0-.6-.4-.8l-.8-.3-1-.2-1-.2-1.1-.3q-.4 0-1-.4l-1-1q-.5-.6-.3-1.7 0-.9.4-1.5t1-1q.7-.5 1.6-.6a9 9 0 0 1 3.4 0q.8.2 1.5.6.7.5 1 1.1t.4 1.7h-3q0-.7-.5-1z" fill="url(#prefix__SVGID_00000097464930856146120360000017786935914121585028_)"/><linearGradient id="prefix__SVGID_00000170993200153936596930000007926605871037966513_" gradientUnits="userSpaceOnUse" x1="39.5" y1="823.4" x2="153.9" y2="807.1" gradientTransform="matrix(1 0 0 -1 0 836.6)"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".5"/></linearGradient><path d="M81.4 17.7v4.5q0 1.5 1.5 1.5H84v2.6h-1.4q-2.4 0-3.4-.9t-1-2.8v-4.9h-1.7v-2.6h1.8v-3h3v3h2.8v2.6z" fill="url(#prefix__SVGID_00000170993200153936596930000007926605871037966513_)"/><linearGradient id="prefix__SVGID_00000167365051421441387240000009302636518302980503_" gradientUnits="userSpaceOnUse" x1="39.3" y1="823.1" x2="154.3" y2="806.8" gradientTransform="matrix(1 0 0 -1 0 836.6)"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".5"/></linearGradient><path d="M88.8 16.5q.8-.8 1.5-1.2.9-.4 2-.4h.5l.5.1v2.7h-1l-1 .2-.9.2-.7.5q-.6.6-.7 1.2l-.1 1.4v5h-3.1v-11h2.9z" fill="url(#prefix__SVGID_00000167365051421441387240000009302636518302980503_)"/><linearGradient id="prefix__SVGID_00000145758855807687895720000007974704795936981908_" gradientUnits="userSpaceOnUse" x1="38.8" y1="820" x2="153.9" y2="803.7" gradientTransform="matrix(1 0 0 -1 0 836.6)"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".5"/></linearGradient><path d="M96.7 23q.8 0 1.4.5t.5 1.3-.5 1.4-1.4.4c-.9 0-1-.1-1.3-.4q-.6-.5-.6-1.4t.6-1.4a2 2 0 0 1 1.3-.4" fill="url(#prefix__SVGID_00000145758855807687895720000007974704795936981908_)"/><linearGradient id="prefix__SVGID_00000135659038945226983540000003972598191897801120_" gradientUnits="userSpaceOnUse" x1="40" y1="826.7" x2="154.7" y2="810.4" gradientTransform="matrix(1 0 0 -1 0 836.6)"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".5"/></linearGradient><path d="M104.2 16.6q.7-.9 1.7-1.3l2-.4q1.2 0 2.3.4t1.8 1.5q.6.7.8 1.7l.3 2.1q0 1.5-.4 2.5-.3 1-1.1 2-.7.6-1.6 1-1 .3-2 .3t-2-.3-1.8-1.3v1.5H101v-15h3zm0 4.1q0 1.5.8 2.2a3 3 0 0 0 2.1.9q.8 0 1.3-.2l.9-.7.5-1a4 4 0 0 0-.6-3.4l-.8-.7-1.3-.2q-.7 0-1.3.2l-.9.7-.5 1q-.3.6-.2 1.2" fill="url(#prefix__SVGID_00000135659038945226983540000003972598191897801120_)"/><linearGradient id="prefix__SVGID_00000075157387643949324390000001055296391136153515_" gradientUnits="userSpaceOnUse" x1="40.1" y1="827.6" x2="154.9" y2="811.3" gradientTransform="matrix(1 0 0 -1 0 836.6)"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".5"/></linearGradient><path d="M120.4 23.8q1.4 0 1.8-.6t.5-1.9v-6.2h3.1v6.3q0 1.1-.2 2l-.8 1.5-1 .8a6 6 0 0 1-2.2.6l-1.2.1q-1.3 0-2.5-.3a4 4 0 0 1-2.7-2.7l-.1-2V15h3v6.2q0 1.2.6 1.8t1.7.7" fill="url(#prefix__SVGID_00000075157387643949324390000001055296391136153515_)"/><linearGradient id="prefix__SVGID_00000159456323314307900080000008768850433047376270_" gradientUnits="userSpaceOnUse" x1="41.7" y1="830.9" x2="155.2" y2="814.8" gradientTransform="matrix(1 0 0 -1 0 836.6)"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".5"/></linearGradient><path d="M130 10q.8 0 1.3.5t.5 1.2-.5 1.4a2 2 0 0 1-1.4.4 2 2 0 0 1-1.3-.4q-.6-.6-.6-1.4t.6-1.2a2 2 0 0 1 1.3-.5m1.5 16.3h-3.1V15h3.1z" fill="url(#prefix__SVGID_00000159456323314307900080000008768850433047376270_)"/><linearGradient id="prefix__SVGID_00000023253636096701048150000016625663227432255139_" gradientUnits="userSpaceOnUse" x1="41.9" y1="831.2" x2="155.3" y2="815" gradientTransform="matrix(1 0 0 -1 0 836.6)"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".5"/></linearGradient><path d="M137.2 26.3h-3v-15h3z" fill="url(#prefix__SVGID_00000023253636096701048150000016625663227432255139_)"/><linearGradient id="prefix__SVGID_00000049908039696513070580000017231736595494291370_" gradientUnits="userSpaceOnUse" x1="40.9" y1="832.9" x2="155.8" y2="816.6" gradientTransform="matrix(1 0 0 -1 0 836.6)"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".5"/></linearGradient><path d="M148.2 25q-.6.7-1.6 1t-2 .4q-1.3 0-2.2-.3a5 5 0 0 1-2.8-3q-.4-1.2-.4-2.3a6 6 0 0 1 1.6-4.4q.7-.7 1.8-1 1-.5 2.4-.5l1.8.3 1.3.8v-4.7h3.2v15h-3zm-3-1.2q1.5 0 2.2-1 .7-.8.7-2 0-1.5-.7-2.3-.7-1-2.2-1-.8 0-1.2.3l-1 .7-.5 1-.2 1.2.2 1.3.5 1 1 .6z" fill="url(#prefix__SVGID_00000049908039696513070580000017231736595494291370_)"/><path d="M17.9 1 1.3 10.8l16.6 9.7 16.5-9.7z" fill="#70429a"/><path class="prefix__st12" d="m17.9 2.7-4.6 2.6v10.8l4.6 2.7 4.6-2.7v-5.4L27 8.1zm-3 6.3a1 1 0 0 1-.9-1q.1-.9 1-1 .9.1 1 1-.1 1-1 1m6.8 1.3L18 8l3.8-2.3L25.5 8z"/><linearGradient id="prefix__SVGID_00000182529429067096568140000007951880934633543559_" gradientUnits="userSpaceOnUse" x1="9.4" y1="825.6" x2="9.4" y2="796.6" gradientTransform="matrix(1 0 0 -1 0 836.6)"><stop offset="0" stop-color="#9b4399"/><stop offset="1" stop-color="#3d1e56"/><stop offset="1" stop-color="#231815"/></linearGradient><path d="M1.2 11v19.3L17.7 40V20.7z" fill="url(#prefix__SVGID_00000182529429067096568140000007951880934633543559_)"/><path class="prefix__st12" d="m15.6 36.2-3-1.8v-.9L6.3 22.8l-.8-.4v8l-2.3-1.3V14.8l3 1.7v1l6.2 10.7.8.4v-8l2.3 1.3z"/><path d="M18 20.7V40l16.6-9.7V11z" fill="#3d1e56"/><path class="prefix__st12" d="m32.5 17.4-2.3-1.3-10 5.8v14.3L30.9 30l1.6-2.7v-3.6l-1.6-.9 1.6-2.7zM30 27.3l-1.1 2-6.5 3.8v-4.5l6.5-3.8 1.2.7zm0-6.3-1 2-6.5 3.8v-4.4l6.5-3.8 1.2.6z"/></svg>
SVG;
$svg_builder_icon = <<<SVG
<svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M10.862 4.195c.26-.26.682-.26.943 0l3.333 3.334c.26.26.26.682 0 .943l-3.333 3.333a.667.667 0 0 1-.943-.943L13.724 8l-2.862-2.862a.667.667 0 0 1 0-.943Z" fill="url(#paint0_linear_502_4159)"/><path fill-rule="evenodd" clip-rule="evenodd" d="M5.138 4.195c.26.26.26.683 0 .943L2.276 8l2.862 2.862a.667.667 0 1 1-.943.943L.862 8.472a.667.667 0 0 1 0-.943l3.333-3.334c.26-.26.683-.26.943 0Z" fill="url(#paint1_linear_502_4159)"/><path fill-rule="evenodd" clip-rule="evenodd" d="M9.478 1.35c.36.08.586.435.506.795l-2.667 12a.667.667 0 1 1-1.301-.29l2.667-12a.667.667 0 0 1 .795-.506Z" fill="url(#paint2_linear_502_4159)"/><defs><linearGradient id="paint0_linear_502_4159" x1="13" y1="4" x2="13" y2="12" gradientUnits="userSpaceOnUse"><stop stop-color="#2EDF95"/><stop offset="1" stop-color="#07847C"/></linearGradient><linearGradient id="paint1_linear_502_4159" x1="3" y1="4" x2="3" y2="12" gradientUnits="userSpaceOnUse"><stop stop-color="#2EDF95"/><stop offset="1" stop-color="#07847C"/></linearGradient><linearGradient id="paint2_linear_502_4159" x1="8" y1="1.333" x2="8" y2="14.667" gradientUnits="userSpaceOnUse"><stop stop-color="#2EDF95"/><stop offset="1" stop-color="#07847C"/></linearGradient></defs></svg>
SVG;
$svg_creator_icon = <<<SVG
<svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_502_4163)"><path opacity=".12" d="M1.333 8A6.667 6.667 0 0 0 8 14.667a2 2 0 0 0 2-2v-.334c0-.31 0-.464.017-.594a2 2 0 0 1 1.722-1.722c.13-.017.285-.017.594-.017h.334a2 2 0 0 0 2-2A6.667 6.667 0 0 0 1.333 8Z" fill="url(#paint0_linear_502_4163)"/><path d="M1.333 8A6.667 6.667 0 0 0 8 14.667a2 2 0 0 0 2-2v-.334c0-.31 0-.464.017-.594a2 2 0 0 1 1.722-1.722c.13-.017.285-.017.594-.017h.334a2 2 0 0 0 2-2A6.667 6.667 0 0 0 1.333 8Z" stroke="url(#paint1_linear_502_4163)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.667 8.667a.667.667 0 1 0 0-1.334.667.667 0 0 0 0 1.334Z" stroke="url(#paint2_linear_502_4163)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.667 6a.667.667 0 1 0 0-1.333.667.667 0 0 0 0 1.333Z" stroke="url(#paint3_linear_502_4163)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.667 5.333a.667.667 0 1 0 0-1.333.667.667 0 0 0 0 1.333Z" stroke="url(#paint4_linear_502_4163)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/></g><defs><linearGradient id="paint0_linear_502_4163" x1="1.333" y1="1.333" x2="16.987" y2="5.151" gradientUnits="userSpaceOnUse"><stop stop-color="#DABD55"/><stop offset="1" stop-color="#F78533"/></linearGradient><linearGradient id="paint1_linear_502_4163" x1="1.333" y1="1.333" x2="16.987" y2="5.151" gradientUnits="userSpaceOnUse"><stop stop-color="#DABD55"/><stop offset="1" stop-color="#F78533"/></linearGradient><linearGradient id="paint2_linear_502_4163" x1="4" y1="7.333" x2="5.565" y2="7.715" gradientUnits="userSpaceOnUse"><stop stop-color="#DABD55"/><stop offset="1" stop-color="#F78533"/></linearGradient><linearGradient id="paint3_linear_502_4163" x1="10" y1="4.667" x2="11.565" y2="5.048" gradientUnits="userSpaceOnUse"><stop stop-color="#DABD55"/><stop offset="1" stop-color="#F78533"/></linearGradient><linearGradient id="paint4_linear_502_4163" x1="6" y1="4" x2="7.565" y2="4.382" gradientUnits="userSpaceOnUse"><stop stop-color="#DABD55"/><stop offset="1" stop-color="#F78533"/></linearGradient><clipPath id="clip0_502_4163"><path fill="#fff" d="M0 0h16v16H0z"/></clipPath></defs></svg>
SVG;
$svg_freeview_icon = <<<SVG
<svg width="16" height="14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.848 12.819 7.246 8.42c.264-.264.396-.396.548-.446a.666.666 0 0 1 .412 0c.152.05.284.182.548.446l4.369 4.368M9.333 9l1.913-1.912c.264-.264.396-.396.548-.446a.666.666 0 0 1 .412 0c.152.05.284.182.548.446L14.667 9m-8-4A1.333 1.333 0 1 1 4 5a1.333 1.333 0 0 1 2.667 0Zm-2.134 8h6.934c1.12 0 1.68 0 2.108-.218a2 2 0 0 0 .874-.874c.218-.428.218-.988.218-2.108V4.2c0-1.12 0-1.68-.218-2.108a2 2 0 0 0-.874-.874C13.147 1 12.587 1 11.467 1H4.533c-1.12 0-1.68 0-2.108.218a2 2 0 0 0-.874.874c-.218.428-.218.988-.218 2.108v5.6c0 1.12 0 1.68.218 2.108a2 2 0 0 0 .874.874c.428.218.988.218 2.108.218Z" stroke="url(#paint0_linear_502_4699)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/><defs><linearGradient id="paint0_linear_502_4699" x1="1" y1="1" x2="15" y2="13" gradientUnits="userSpaceOnUse"><stop stop-color="#EC46FB"/><stop offset="1" stop-color="#5E44FF"/></linearGradient></defs></svg>
SVG;
$svg_memestr_icon = <<<SVG
<svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_502_4658)"><path d="M5.333 9.333s1 1.334 2.667 1.334c1.667 0 2.667-1.334 2.667-1.334M10 6h.007M6 6h.007m8.66 2A6.667 6.667 0 1 1 1.333 8a6.667 6.667 0 0 1 13.334 0Zm-4.334-2a.333.333 0 1 1-.666 0 .333.333 0 0 1 .666 0Zm-4 0a.333.333 0 1 1-.666 0 .333.333 0 0 1 .666 0Z" stroke="url(#paint0_linear_502_4658)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/></g><defs><linearGradient id="paint0_linear_502_4658" x1="1" y1="1.333" x2="16.225" y2="13.079" gradientUnits="userSpaceOnUse"><stop stop-color="#FCFF6D"/><stop offset="1" stop-color="#C8FFA7"/></linearGradient><clipPath id="clip0_502_4658"><path fill="#fff" d="M0 0h16v16H0z"/></clipPath></defs></svg>
SVG;
$svg_about_icon = <<<SVG
<svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7.333 7.333h-3.2c-.746 0-1.12 0-1.405.146-.25.127-.455.331-.583.582C2 8.347 2 8.72 2 9.467V14m12 0V4.133c0-.746 0-1.12-.145-1.405a1.333 1.333 0 0 0-.583-.583C12.987 2 12.613 2 11.867 2h-2.4c-.747 0-1.12 0-1.406.145-.25.128-.455.332-.582.583-.146.285-.146.659-.146 1.405V14m7.334 0H1.333m8.334-9.333h2m-2 2.666h2m-2 2.667h2" stroke="url(#paint0_linear_502_4738)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/><defs><linearGradient id="paint0_linear_502_4738" x1="1" y1="2" x2="15" y2="14" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".4"/></linearGradient></defs></svg>
SVG;
$svg_edu_icon = <<<SVG
<svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13.333 12.667v-2H4.667a2 2 0 0 0-2 2m3.2 2H11.2c.747 0 1.12 0 1.405-.146.251-.128.455-.332.583-.582.145-.286.145-.659.145-1.406V3.467c0-.747 0-1.12-.145-1.406a1.333 1.333 0 0 0-.583-.582c-.285-.146-.658-.146-1.405-.146H5.867c-1.12 0-1.68 0-2.108.218a2 2 0 0 0-.874.874c-.218.428-.218.988-.218 2.108v6.934c0 1.12 0 1.68.218 2.108a2 2 0 0 0 .874.874c.427.218.988.218 2.108.218Z" stroke="url(#paint0_linear_502_4618)" stroke-width="1.333" stroke-linecap="round" stroke-linejoin="round"/><defs><linearGradient id="paint0_linear_502_4618" x1="2.4" y1="1.333" x2="16.47" y2="10.016" gradientUnits="userSpaceOnUse"><stop stop-color="#46B5F3"/><stop offset="1" stop-color="#283CF0"/></linearGradient></defs></svg>
SVG;
$svg_plans_icon = <<<SVG
<svg width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
SVG;
$svg_menu_icon = <<<SVG
<svg width="24" height="24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M2 12a1 1 0 0 1 1-1h18a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1ZM2 6a1 1 0 0 1 1-1h18a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1ZM2 18a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1Z" fill="#D0BED8"/></svg>
SVG;
$svg_delete_icon = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-trash-2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg>
SVG;
?>

<nav class="navigation_header">
  <a href="https://nostr.build" class="">
    <?= $svg_logo ?>
  </a>
  <div class="center_buttons">
    <a href="/plans/" class="nav_button">
      <?= $svg_plans_icon ?>
      Plans
    </a>
    <a href="/features" class="nav_button">
      <?= $svg_builder_icon ?>
      Features
    </a>
    <a href="/creators" class="nav_button">
      <?= $svg_creator_icon ?>
      Creators
    </a>
    <?php if ($perm->isGuest()) : ?>
      <a href="/freeview" class="nav_button">
        <?= $svg_freeview_icon ?>
        Free View
      </a>
    <?php else : ?>
      <a href="/viewall" class="nav_button">
        <?= $svg_freeview_icon ?>
        View All
      </a>
    <?php endif; ?>
    <a href="https://btcpay.nostr.build/apps/3uzdJNJRgk94uXkyEFTRjxKx5c4H/pos" class="nav_button">
      <?= $svg_memestr_icon ?>
      Shop
    </a>
    <a href="/delete/" class="nav_button">
      <?= $svg_delete_icon ?>
      Delete
    </a>
    <a href="https://blossom.band" class="nav_button">
      🌸
      Blossom
    </a>
  </div>
  <?php if ($perm->isGuest()) : ?>
    <a href="/login" class="nav_button login_button">
      Signup/Login
    </a>
  <?php else : ?>
    <a href="/account/" class="nav_button login_button">
      Account
      <img src="<?= $ppic ?>" alt="user image" style="width:33px;height:33px;border-radius:50%;">
    </a>
  <?php endif; ?>
  <button class="menu_button">
    <?= $svg_menu_icon ?>
  </button>
</nav>
</header>
<nav>
  <div class="menu">
    <a href="/plans/" class="nav_button">
      <span>
        <?= $svg_plans_icon ?>
      </span>
      Plans
    </a>
    <a href="/features" class="nav_button">
      <span>
        <?= $svg_builder_icon ?>
      </span>
      Features
    </a>
    <a href="/creators" class="nav_button">
      <span>
        <?= $svg_creator_icon ?>
      </span>
      Creators
    </a>
    <?php if ($perm->isGuest()) : ?>
      <a href="/freeview" class="nav_button">
        <span>
          <?= $svg_freeview_icon ?>
        </span>
        Free View
      </a>
    <?php else : ?>
      <a href="/viewall" class="nav_button">
        <span>
          <?= $svg_freeview_icon ?>
        </span>
        View All
      </a>
    <?php endif; ?>
    <a href="https://btcpay.nostr.build/apps/3uzdJNJRgk94uXkyEFTRjxKx5c4H/pos" class="nav_button">
      <span>
        <?= $svg_memestr_icon ?>
      </span>
      Shop
    </a>
    <a href="/about" class="nav_button">
      <span>
        <?= $svg_about_icon ?>
      </span>
      About
    </a>
    <a href="/delete/" class="nav_button">
      <span>
        <?= $svg_delete_icon ?>
      </span>
      Delete
    </a>
    <a href="https://blossom.band" class="nav_button">
      <span>
        🌸
      </span>
      Blossom
    </a>
    <?php if ($perm->isGuest()) : ?>
      <a href="/login" class="nav_button login_button login_desktop">
        <span>
          Signup/Login
        </span>
      </a>
    <?php else : ?>
      <a href="/account/" class="nav_button login_button login_desktop">
        <span style="display: flex; align-items: center;">
          <span style="margin-right: 10px;">Account</span>
          <img src="<?= $ppic ?>" alt="user image" style="width:33px;height:33px;border-radius:50%">
        </span>
      </a>
    <?php endif; ?>
  </div>
</nav>
