<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
// Instantiate the permissions class
$perm = new Permission();

$ppic = (isset($_SESSION["ppic"]) && !empty($_SESSION["ppic"])) ? $_SESSION["ppic"] : "https://nostr.build/assets/temp_ppic.png";

$svg_logo = <<<SVG
<svg width="122" height="23" fill="none" xmlns="http://www.w3.org/2000/svg" opacity=".92" xmlns:v="https://vecta.io/nano"><linearGradient id="A" gradientUnits="userSpaceOnUse" x1="23.491" y1="11.087" x2="121.217" y2="24.995"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M27.37 8.29c.8-.92 1.79-1.38 2.96-1.39.64-.01 1.2.09 1.69.3.49.2.88.48 1.18.84.36.42.61.9.75 1.46.14.55.21 1.16.21 1.84v5.15H31.5v-4.96c0-.33-.04-.65-.11-.94a1.88 1.88 0 0 0-.34-.78c-.16-.23-.39-.4-.66-.5-.29-.11-.59-.17-.9-.16-.43.01-.79.1-1.09.27-.28.15-.5.38-.66.64-.16.27-.26.56-.32.86-.05.31-.08.62-.08.94v4.64H24.7V7.1h2.53l.14 1.19z" fill="url(#A)"/><linearGradient id="B" gradientUnits="userSpaceOnUse" x1="23.725" y1="9.339" x2="121.946" y2="23.317"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M40.79 16.63c-1.6 0-2.83-.43-3.7-1.28-.86-.87-1.29-2.07-1.29-3.6 0-.74.11-1.4.34-2 .24-.61.56-1.12.98-1.55a4.05 4.05 0 0 1 1.56-.96c.61-.23 1.32-.34 2.11-.34.78 0 1.47.11 2.08.34.63.21 1.15.53 1.58.96.44.43.77.94.99 1.55.24.59.36 1.26.36 2 0 1.55-.44 2.75-1.31 3.6s-2.1 1.28-3.7 1.28h0zm0-7.49c-.81 0-1.41.25-1.8.75s-.58 1.12-.58 1.87c0 .76.19 1.39.58 1.89s.99.75 1.8.75 1.41-.25 1.78-.75.56-1.13.56-1.89c0-.75-.19-1.37-.56-1.87s-.97-.75-1.78-.75h0z" fill="url(#B)"/><linearGradient id="C" gradientUnits="userSpaceOnUse" x1="24.018" y1="7.887" x2="121.99" y2="21.83"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M51.14 8.91c-.19 0-.37.01-.54.04a1.66 1.66 0 0 0-.45.12c-.14.05-.26.13-.34.25a.55.55 0 0 0-.11.43.61.61 0 0 0 .24.48c.16.12.39.21.68.29l.92.21.99.2.96.25c.31.1.59.21.83.36.33.19.59.46.81.8.21.34.31.77.3 1.27 0 .49-.09.91-.26 1.27-.16.34-.39.64-.68.89-.36.31-.82.53-1.37.68-.55.13-1.11.2-1.67.2-.65 0-1.25-.07-1.8-.2a4.04 4.04 0 0 1-1.48-.7 3.43 3.43 0 0 1-.83-.91c-.21-.37-.34-.81-.38-1.32h2.66c.08.4.28.68.62.84.35.15.76.23 1.24.23l.43-.02c.15-.02.29-.06.43-.11a.86.86 0 0 0 .34-.25.58.58 0 0 0 .15-.37c.01-.31-.1-.53-.34-.66-.22-.13-.47-.22-.73-.27l-.84-.18-.9-.18-.86-.21c-.28-.09-.54-.22-.79-.37a2.91 2.91 0 0 1-.92-.86c-.24-.36-.34-.83-.3-1.41.03-.51.16-.94.39-1.3a2.82 2.82 0 0 1 .92-.87 4.01 4.01 0 0 1 1.26-.48 7.03 7.03 0 0 1 1.44-.14 6.75 6.75 0 0 1 1.52.16c.44.09.87.27 1.24.52.36.24.65.55.86.93.23.38.35.84.38 1.39H52.6c-.05-.39-.21-.65-.49-.78-.26-.15-.58-.22-.97-.22z" fill="url(#C)"/><linearGradient id="D" gradientUnits="userSpaceOnUse" x1="24.579" y1="5.43" x2="122.058" y2="19.303"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M60.22 9.28v3.8c0 .81.43 1.21 1.29 1.21h.98v2.19h-1.24c-1.31.05-2.26-.19-2.83-.71-.56-.52-.84-1.31-.84-2.37V9.28h-1.5V7.09h1.5V4.54h2.65v2.55h2.36v2.19c0 0-2.37 0-2.37 0z" fill="url(#D)"/><linearGradient id="E" gradientUnits="userSpaceOnUse" x1="24.36" y1="5.637" x2="122.48" y2="19.601"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M66.6 8.27c.4-.45.82-.79 1.28-1.02s1.01-.34 1.65-.34l.47.02c.15.01.29.03.41.05V9.3l-.88.02a4.5 4.5 0 0 0-.83.07c-.27.05-.52.13-.77.23a1.95 1.95 0 0 0-.64.43c-.29.31-.47.64-.54 1a7.37 7.37 0 0 0-.09 1.21v4.23h-2.65v-9.4h2.44l.15 1.18h0z" fill="url(#E)"/><linearGradient id="F" gradientUnits="userSpaceOnUse" x1="23.931" y1="8.046" x2="122.154" y2="22.025"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M73.36 13.53c.48 0 .86.14 1.16.41s.45.66.45 1.16-.15.88-.45 1.14-.69.39-1.16.39c-.48 0-.87-.13-1.18-.39-.3-.26-.45-.64-.45-1.14s.15-.89.45-1.16c.31-.28.7-.41 1.18-.41z" fill="url(#F)"/><linearGradient id="G" gradientUnits="userSpaceOnUse" x1="25.027" y1="2.584" x2="122.815" y2="16.5"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M79.7 8.29c.39-.45.9-.8 1.46-1.02.56-.23 1.16-.36 1.76-.37.67-.02 1.34.1 1.95.36.6.24 1.1.64 1.5 1.21.33.42.56.91.71 1.48.15.58.23 1.17.23 1.77 0 .77-.1 1.48-.3 2.12-.18.61-.51 1.17-.98 1.62-.39.39-.87.7-1.41.89-.52.19-1.07.28-1.63.29-.61 0-1.23-.1-1.8-.3-.55-.21-1.05-.58-1.5-1.09v1.25h-2.65V3.86h2.65l.01 4.43h0zm0 3.53c.01.76.24 1.38.68 1.85.45.48 1.07.72 1.86.73.43.01.79-.05 1.09-.18s.54-.31.73-.55a2.52 2.52 0 0 0 .45-.86 3.21 3.21 0 0 0 .17-1.05c0-.37-.06-.71-.17-1.03a2.19 2.19 0 0 0-.47-.84 1.95 1.95 0 0 0-.75-.55c-.3-.13-.66-.2-1.09-.2a2.75 2.75 0 0 0-1.09.21c-.31.14-.58.34-.79.59-.2.24-.36.52-.47.86a3.5 3.5 0 0 0-.15 1.02z" fill="url(#G)"/><linearGradient id="H" gradientUnits="userSpaceOnUse" x1="25.108" y1="1.833" x2="123.008" y2="15.766"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M93.56 14.4c.73 0 1.24-.18 1.54-.53.3-.37.44-.89.41-1.57V7.09h2.65v5.28c0 .65-.07 1.21-.21 1.68-.12.46-.35.89-.66 1.27-.24.29-.5.51-.79.66a5.1 5.1 0 0 1-.9.39c-.31.1-.65.16-1.01.2a7.13 7.13 0 0 1-1.03.07c-.78 0-1.48-.1-2.12-.3-.62-.19-1.17-.54-1.6-1.02a3.26 3.26 0 0 1-.68-1.27c-.13-.46-.19-1.02-.19-1.68V7.09h2.65v5.21c-.01.68.14 1.19.45 1.55.32.36.82.54 1.49.55h0z" fill="url(#H)"/><linearGradient id="I" gradientUnits="userSpaceOnUse" x1="26.694" y1="-.985" x2="123.147" y2="12.742"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M101.68 2.72c.49 0 .88.14 1.18.41s.45.64.45 1.09c0 .48-.15.85-.45 1.12-.3.26-.69.39-1.18.39-.48 0-.87-.13-1.18-.39-.3-.27-.45-.65-.45-1.12 0-.45.15-.81.45-1.09.31-.27.71-.41 1.18-.41zm1.34 13.77h-2.65v-9.4h2.65v9.4z" fill="url(#I)"/><linearGradient id="J" gradientUnits="userSpaceOnUse" x1="26.931" y1="-1.159" x2="123.248" y2="12.548"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M107.9,16.49h-2.65V3.86h2.65V16.49z" fill="url(#J)"/><linearGradient id="K" gradientUnits="userSpaceOnUse" x1="25.749" y1="-2.708" x2="123.737" y2="11.237"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M117.27 15.44a3.83 3.83 0 0 1-1.35.89c-.49.18-1.08.28-1.78.3a5.48 5.48 0 0 1-1.8-.29c-.54-.19-1.03-.5-1.43-.89-.47-.47-.82-1.04-1.01-1.66-.19-.63-.28-1.27-.28-1.93 0-1.53.45-2.76 1.35-3.69.4-.4.9-.72 1.5-.94s1.28-.34 2.05-.34c.58 0 1.09.08 1.54.25a2.89 2.89 0 0 1 1.16.7V3.86h2.66v12.62h-2.46l-.15-1.04zm-2.52-1.04c.8-.02 1.41-.28 1.82-.77.43-.5.64-1.11.64-1.84 0-.78-.21-1.42-.64-1.91s-1.04-.74-1.86-.75c-.41 0-.78.07-1.09.2a2.18 2.18 0 0 0-.77.57c-.2.24-.35.52-.45.84a3.5 3.5 0 0 0-.15 1.02c0 .39.05.75.15 1.07.1.31.25.58.45.82.21.24.48.42.79.55.33.14.7.2 1.11.2z" fill="url(#K)"/><path d="M10.69.22L.23 5.93l10.46 5.71 10.46-5.71L10.69.22z" fill="#6f4099"/><path d="M10.68 1.17L7.79 2.75V9.1l2.91 1.59 2.9-1.59V5.93l2.91-1.59c0 .01-5.83-3.17-5.83-3.17zm-1.8 3.75c-.33 0-.61-.26-.61-.57 0-.32.27-.57.61-.57.33 0 .61.26.61.57-.01.31-.28.57-.61.57zm4.24.75l-2.44-1.31 2.44-1.33 2.42 1.32-2.42 1.32h0z" fill="#fff"/><linearGradient id="L" gradientUnits="userSpaceOnUse" x1="5.367" y1="6.091" x2="5.367" y2="23.218"><stop offset="0" stop-color="#9a4098"/><stop offset="1" stop-color="#3b1e56"/><stop offset="1" stop-color="#231714"/></linearGradient><path d="M.13 6.09v11.42l10.46 5.71V11.8L.13 6.09z" fill="url(#L)"/><path d="M9.24 21L7.3 19.94v-.53l-3.87-6.34-.49-.27v4.76l-1.45-.79V8.31l1.94 1.06v.53l3.87 6.34.49.26v-4.75l1.45.79z" fill="#fff"/><path d="M10.79 11.8v11.42l10.46-5.71V6.09L10.79 11.8h0z" fill="#3b1e56"/><path d="M19.9 9.9l-1.45-.8-6.3 3.44V21l6.78-3.7.97-1.59V13.6l-.97-.53.97-1.59V9.9h0zm-1.45 5.81l-.73 1.19-4.12 2.25V16.5l4.12-2.25.73.4v1.06h0zm0-3.7l-.73 1.19-4.12 2.25V12.8l4.12-2.25.73.4v1.06h0z" fill="#fff"/></svg>
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
