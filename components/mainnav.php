<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
// Instantiate the permissions class
$perm = new Permission();

$ppic = (isset($_SESSION["ppic"]) && !empty($_SESSION["ppic"])) ? $_SESSION["ppic"] : "https://nostr.build/assets/temp_ppic.png";

$svg_logo = <<<SVG
<svg width="123" height="19" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.752 7.488C4.776 6.256 6.04 5.632 7.544 5.616c.816-.016 1.536.12 2.16.408.624.272 1.128.648 1.512 1.128.464.56.784 1.216.96 1.968.176.736.264 1.56.264 2.472v6.936H9.032v-6.672c0-.448-.048-.872-.144-1.272a2.625 2.625 0 0 0-.432-1.056 1.835 1.835 0 0 0-.84-.672 2.9 2.9 0 0 0-1.152-.216c-.544.016-1.008.136-1.392.36a2.287 2.287 0 0 0-.84.864 3.64 3.64 0 0 0-.408 1.152c-.064.416-.096.84-.096 1.272v6.24H.344V5.88h3.24l.168 1.608Z" fill="url(#a)"/><path d="M20.92 18.72c-2.049 0-3.625-.576-4.729-1.728-1.104-1.168-1.656-2.784-1.656-4.848 0-.992.144-1.888.432-2.688.304-.816.72-1.512 1.248-2.088a5.126 5.126 0 0 1 1.992-1.296c.8-.304 1.704-.456 2.712-.456.992 0 1.88.152 2.664.456.8.288 1.472.72 2.016 1.296.56.576.984 1.272 1.272 2.088.304.8.456 1.696.456 2.688 0 2.08-.56 3.696-1.68 4.848-1.104 1.152-2.68 1.728-4.728 1.728Zm0-10.08c-1.04 0-1.809.336-2.305 1.008-.496.672-.744 1.512-.744 2.52 0 1.024.248 1.872.744 2.544.496.672 1.264 1.008 2.304 1.008 1.04 0 1.8-.336 2.28-1.008.48-.672.72-1.52.72-2.544 0-1.008-.24-1.848-.72-2.52-.48-.672-1.24-1.008-2.28-1.008Z" fill="url(#b)"/><path d="M34.156 8.328c-.24 0-.472.016-.696.048a2.19 2.19 0 0 0-.576.168.89.89 0 0 0-.432.336c-.112.144-.16.336-.144.576a.87.87 0 0 0 .312.648c.208.16.496.288.864.384.352.096.744.192 1.176.288.432.08.856.168 1.272.264.416.096.824.208 1.224.336.4.128.752.288 1.056.48.416.256.76.616 1.032 1.08.272.464.4 1.032.384 1.704 0 .656-.112 1.224-.336 1.704a3.6 3.6 0 0 1-.864 1.2c-.464.416-1.048.72-1.752.912a8.775 8.775 0 0 1-2.136.264c-.832 0-1.6-.088-2.304-.264a5.133 5.133 0 0 1-1.896-.936 4.595 4.595 0 0 1-1.056-1.224c-.272-.496-.432-1.088-.48-1.776h3.408c.096.544.36.92.792 1.128.448.208.976.312 1.584.312.176 0 .36-.008.552-.024a2.52 2.52 0 0 0 .552-.144c.176-.08.32-.192.432-.336a.796.796 0 0 0 .192-.504c.016-.416-.128-.712-.432-.888a2.775 2.775 0 0 0-.936-.36c-.336-.08-.696-.16-1.08-.24l-1.152-.24c-.368-.08-.736-.176-1.104-.288a4.247 4.247 0 0 1-1.008-.504 3.84 3.84 0 0 1-1.176-1.152c-.304-.48-.432-1.112-.384-1.896.032-.688.2-1.272.504-1.752.32-.496.712-.888 1.176-1.176a4.921 4.921 0 0 1 1.608-.648 8.946 8.946 0 0 1 1.848-.192c.688 0 1.336.072 1.944.216a4.463 4.463 0 0 1 1.584.696c.464.32.832.736 1.104 1.248.288.512.448 1.136.48 1.872h-3.264c-.064-.528-.272-.88-.624-1.056-.336-.176-.752-.264-1.248-.264Z" fill="url(#c)"/><path d="M45.775 8.832v5.112c0 1.088.552 1.632 1.656 1.632h1.248v2.952h-1.585c-1.68.064-2.888-.256-3.623-.96-.72-.704-1.08-1.768-1.08-3.192V8.832h-1.92V5.88h1.92V2.448h3.384V5.88h3.023v2.952h-3.023Z" fill="url(#d)"/><path d="M53.93 7.464c.511-.608 1.055-1.064 1.631-1.368.592-.304 1.296-.456 2.112-.456.208 0 .408.008.6.024s.368.04.528.072v3.12c-.368 0-.744.008-1.128.024-.368 0-.72.032-1.056.096a4.395 4.395 0 0 0-.984.312c-.304.128-.576.32-.816.576-.368.416-.6.864-.696 1.344-.08.48-.12 1.024-.12 1.632v5.688h-3.384V5.88h3.12l.192 1.584Z" fill="url(#e)"/><path d="M62.572 14.544c.608 0 1.104.184 1.488.552.384.368.576.888.576 1.56s-.192 1.184-.576 1.536c-.384.352-.88.528-1.488.528s-1.112-.176-1.512-.528c-.384-.352-.576-.864-.576-1.536s.192-1.192.576-1.56c.4-.368.904-.552 1.512-.552Z" fill="url(#f)"/><path d="M70.689 7.488A4.816 4.816 0 0 1 72.56 6.12a6.056 6.056 0 0 1 2.256-.504 5.89 5.89 0 0 1 2.496.48c.768.32 1.408.864 1.92 1.632.416.56.72 1.224.912 1.992a9.57 9.57 0 0 1 .288 2.376c0 1.04-.128 1.992-.384 2.856a5.045 5.045 0 0 1-1.248 2.184 4.812 4.812 0 0 1-1.8 1.2 5.946 5.946 0 0 1-2.088.384 6.465 6.465 0 0 1-2.304-.408c-.704-.288-1.344-.776-1.92-1.464v1.68h-3.384V1.536h3.384v5.952Zm0 4.752c.016 1.024.304 1.856.864 2.496.576.64 1.368.968 2.376.984.544.016 1.008-.064 1.392-.24.384-.176.696-.424.936-.744.256-.336.448-.72.576-1.152.144-.432.216-.904.216-1.416 0-.496-.072-.96-.216-1.392a3.06 3.06 0 0 0-.6-1.128 2.594 2.594 0 0 0-.96-.744c-.384-.176-.848-.264-1.392-.264-.512 0-.976.096-1.392.288-.4.192-.736.456-1.008.792-.256.32-.456.704-.6 1.152a4.791 4.791 0 0 0-.192 1.368Z" fill="url(#g)"/><path d="M88.418 15.72c.928 0 1.584-.24 1.968-.72.384-.496.56-1.2.528-2.112V5.88h3.384v7.104c0 .88-.088 1.632-.264 2.256a4.593 4.593 0 0 1-.84 1.704c-.304.384-.64.68-1.008.888a6.323 6.323 0 0 1-1.152.528c-.4.128-.832.216-1.296.264a9.327 9.327 0 0 1-1.32.096c-.992 0-1.896-.136-2.712-.408a4.485 4.485 0 0 1-2.04-1.368 4.413 4.413 0 0 1-.864-1.704c-.16-.624-.24-1.376-.24-2.256V5.88h3.384v7.008c-.016.912.176 1.608.576 2.088.4.48 1.032.728 1.896.744Z" fill="url(#h)"/><path d="M98.802 0c.624 0 1.128.184 1.512.552.384.368.576.856.576 1.464 0 .64-.192 1.144-.576 1.512-.384.352-.888.528-1.512.528-.608 0-1.112-.176-1.512-.528-.384-.368-.576-.872-.576-1.512 0-.608.192-1.096.576-1.464.4-.368.904-.552 1.512-.552Zm1.704 18.528h-3.384V5.88h3.384v12.648Z" fill="url(#i)"/><path d="M106.759 18.528h-3.384V1.536h3.384v16.992Z" fill="url(#j)"/><path d="M118.734 17.112a4.822 4.822 0 0 1-1.728 1.2c-.624.24-1.384.376-2.28.408-.832 0-1.6-.128-2.304-.384a5.035 5.035 0 0 1-1.824-1.2 5.508 5.508 0 0 1-1.296-2.232 9.473 9.473 0 0 1-.36-2.592c0-2.064.576-3.72 1.728-4.968.512-.544 1.152-.968 1.92-1.272.768-.304 1.64-.456 2.616-.456.736 0 1.392.112 1.968.336.592.208 1.088.52 1.488.936V1.536h3.408v16.992h-3.144l-.192-1.416Zm-3.216-1.392c1.024-.032 1.8-.376 2.328-1.032.544-.672.816-1.496.816-2.472 0-1.056-.272-1.912-.816-2.568-.544-.656-1.336-.992-2.376-1.008-.528 0-.992.088-1.392.264a2.7 2.7 0 0 0-.984.768c-.256.32-.448.696-.576 1.128a4.634 4.634 0 0 0-.192 1.368c0 .528.064 1.008.192 1.44.128.416.32.784.576 1.104.272.32.608.568 1.008.744.416.176.888.264 1.416.264Z" fill="url(#k)"/><defs><linearGradient id="a" x1="0" y1="2.5" x2="125.8" y2="20.403" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><linearGradient id="b" x1="0" y1="2.5" x2="125.8" y2="20.403" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><linearGradient id="c" x1="0" y1="2.5" x2="125.8" y2="20.403" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><linearGradient id="d" x1="0" y1="2.5" x2="125.8" y2="20.403" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><linearGradient id="e" x1="0" y1="2.5" x2="125.8" y2="20.403" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><linearGradient id="f" x1="0" y1="2.5" x2="125.8" y2="20.403" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><linearGradient id="g" x1="0" y1="2.5" x2="125.8" y2="20.403" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><linearGradient id="h" x1="0" y1="2.5" x2="125.8" y2="20.403" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><linearGradient id="i" x1="0" y1="2.5" x2="125.8" y2="20.403" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><linearGradient id="j" x1="0" y1="2.5" x2="125.8" y2="20.403" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><linearGradient id="k" x1="0" y1="2.5" x2="125.8" y2="20.403" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient></defs></svg>
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
$svg_menu_icon = <<<SVG
<svg width="24" height="24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M2 12a1 1 0 0 1 1-1h18a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1ZM2 6a1 1 0 0 1 1-1h18a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1ZM2 18a1 1 0 0 1 1-1h12a1 1 0 1 1 0 2H3a1 1 0 0 1-1-1Z" fill="#D0BED8"/></svg>
SVG;
?>

<nav class="navigation_header">
  <a href="https://nostr.build" class="">
    <?= $svg_logo ?>
  </a>
  <div class="center_buttons">
    <a href="/builders" class="nav_button">
      <?= $svg_builder_icon ?>
      Builders
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
    <a href="https://shop.nostr.build" class="nav_button">
      <?= $svg_memestr_icon ?>
      Shop
    </a>
    <a href="/edu" class="nav_button">
      <?= $svg_edu_icon ?>
      Edu
    </a>
    <a href="/about" class="nav_button">
      <?= $svg_about_icon ?>
      About
    </a>
  </div>
  <?php if ($perm->isGuest()) : ?>
    <a href="/login" class="nav_button login_button">
      Signup/Login
    </a>
  <?php else : ?>
    <a href="/account" class="nav_button login_button">
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
    <a href="/builders" class="nav_button">
      <span>
        <?= $svg_builder_icon ?>
      </span>
      Builders
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
    <a href="https://shop.nostr.build" class="nav_button">
      <span>
        <?= $svg_memestr_icon ?>
      </span>
      Shop
    </a>
    <a href="/edu" class="nav_button">
      <span>
        <?= $svg_edu_icon ?>
      </span>
      Edu
    </a>
    <a href="/about" class="nav_button">
      <span>
        <?= $svg_about_icon ?>
      </span>
      About
    </a>
    <?php if ($perm->isGuest()) : ?>
      <a href="/login" class="nav_button login_button login_desktop">
        <span>
          Signup/Login
        </span>
      </a>
    <?php else : ?>
      <a href="/account" class="nav_button login_button login_desktop">
        <span style="display: flex; align-items: center;">
          <span style="margin-right: 10px;">Account</span>
          <img src="<?= $ppic ?>" alt="user image" style="width:33px;height:33px;border-radius:50%">
        </span>
      </a>
    <?php endif; ?>
  </div>
  <?php if ($perm->isGuest()) { ?>
    <a class="ref_link" style="font-size: large" href="https://nostr.build/signup/new/"><img src="https://i.nostr.build/0Q82.png" style="width:220px;" alt="Premium Accounts"></a>
    <?php } ?>
</nav>
