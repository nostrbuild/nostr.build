<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
// Instantiate the permissions class
$perm = new Permission();

$ppic = (isset($_SESSION["ppic"]) && !empty($_SESSION["ppic"])) ? $_SESSION["ppic"] : "https://nostr.build/assets/temp_ppic.png";

$svg_logo = <<<SVG
<svg width="125" height="29" fill="none" xmlns="http://www.w3.org/2000/svg" xmlns:v="https://vecta.io/nano"><linearGradient id="A" gradientUnits="userSpaceOnUse" x1="24.652" y1="13.466" x2="126.436" y2="27.951"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M28.7 10.39c.83-.99 1.85-1.5 3.07-1.51.66-.01 1.24.1 1.75.33.51.22.91.52 1.22.91.38.45.63.98.78 1.59a8.71 8.71 0 0 1 .21 2v5.6h-2.76v-5.39c0-.36-.04-.7-.12-1.03-.05-.31-.17-.6-.35-.85a1.51 1.51 0 0 0-.68-.54 2.29 2.29 0 0 0-.93-.17c-.44.01-.82.11-1.13.29a1.83 1.83 0 0 0-.68.7c-.16.29-.27.6-.33.93-.05.34-.08.68-.08 1.03v5.04h-2.74V9.1h2.62l.15 1.29z" fill="url(#A)"/><linearGradient id="B" gradientUnits="userSpaceOnUse" x1="24.908" y1="11.661" x2="126.716" y2="26.15"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M42.59 19.46c-1.66 0-2.93-.47-3.83-1.4-.89-.94-1.34-2.25-1.34-3.91 0-.8.12-1.52.35-2.17.25-.66.58-1.22 1.01-1.69.45-.47 1-.83 1.61-1.05.65-.25 1.38-.37 2.19-.37.8 0 1.52.12 2.16.37a4.03 4.03 0 0 1 1.63 1.05c.45.47.8 1.03 1.03 1.69.25.65.37 1.37.37 2.17 0 1.68-.45 2.98-1.36 3.91-.88.94-2.16 1.4-3.82 1.4h0zm0-8.13c-.84 0-1.46.27-1.87.81-.4.54-.6 1.22-.6 2.03 0 .83.2 1.51.6 2.05s1.02.81 1.86.81 1.46-.27 1.85-.81.58-1.23.58-2.05c0-.81-.19-1.49-.58-2.03-.38-.54-1-.81-1.84-.81h0z" fill="url(#B)"/><linearGradient id="C" gradientUnits="userSpaceOnUse" x1="25.128" y1="10.147" x2="126.923" y2="24.633"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M53.31 11.07c-.19 0-.38.01-.56.04a1.59 1.59 0 0 0-.47.14c-.14.05-.26.15-.35.27s-.13.27-.12.47c0 .2.1.39.25.52.17.13.4.23.7.31l.95.23 1.03.21c.34.07.67.17.99.27s.61.23.85.39c.34.21.62.5.84.87s.32.83.31 1.38c0 .53-.09.99-.27 1.38-.16.37-.4.7-.7.97-.38.34-.85.58-1.42.74a7.25 7.25 0 0 1-1.73.21c-.67 0-1.29-.07-1.86-.21a4 4 0 0 1-1.53-.76c-.34-.28-.63-.61-.85-.99-.22-.4-.35-.88-.39-1.43h2.76c.08.44.29.74.64.91.36.17.79.25 1.28.25l.45-.02c.15-.02.3-.06.45-.12a.94.94 0 0 0 .35-.27c.1-.11.15-.26.16-.41.01-.34-.1-.57-.35-.72a2.26 2.26 0 0 0-.76-.29l-.87-.19-.93-.19c-.3-.06-.6-.14-.89-.23a3.7 3.7 0 0 1-.82-.41c-.38-.24-.71-.56-.95-.93-.25-.39-.35-.9-.31-1.53.03-.56.16-1.03.41-1.41.26-.4.58-.72.95-.95.4-.25.84-.43 1.3-.52.49-.1.99-.16 1.5-.16.56 0 1.08.06 1.57.17a3.5 3.5 0 0 1 1.28.56c.38.26.67.59.89 1.01.23.41.36.92.39 1.51h-2.64c-.05-.43-.22-.71-.51-.85-.29-.15-.62-.22-1.02-.22z" fill="url(#C)"/><linearGradient id="D" gradientUnits="userSpaceOnUse" x1="25.513" y1="7.51" x2="127.285" y2="21.993"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M62.71 11.48v4.13c0 .88.45 1.32 1.34 1.32h1.01v2.38h-1.28c-1.36.05-2.34-.21-2.93-.78-.58-.57-.87-1.43-.87-2.58v-4.48h-1.55V9.1h1.55V6.33h2.74V9.1h2.45v2.38h-2.46z" fill="url(#D)"/><linearGradient id="E" gradientUnits="userSpaceOnUse" x1="25.461" y1="7.812" x2="127.264" y2="22.3"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M69.31 10.38c.41-.49.85-.86 1.32-1.1.48-.25 1.05-.37 1.71-.37l.49.02a2.62 2.62 0 0 1 .43.06v2.52l-.91.02c-.3 0-.58.03-.85.08s-.54.14-.8.25c-.25.1-.47.26-.66.47-.3.34-.49.7-.56 1.09-.06.39-.1.83-.1 1.32v4.59h-2.74V9.1h2.53l.14 1.28h0z" fill="url(#E)"/><linearGradient id="F" gradientUnits="userSpaceOnUse" x1="25.076" y1="10.675" x2="126.884" y2="25.164"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M76.3 16.28c.49 0 .89.15 1.2.45s.47.72.47 1.26-.16.96-.47 1.24-.71.43-1.2.43-.9-.14-1.22-.43c-.31-.28-.47-.7-.47-1.24s.16-.96.47-1.26c.32-.3.73-.45 1.22-.45z" fill="url(#F)"/><linearGradient id="G" gradientUnits="userSpaceOnUse" x1="25.938" y1="4.541" x2="127.724" y2="19.027"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M82.87 10.39c.41-.49.93-.86 1.51-1.1.58-.25 1.2-.39 1.83-.41a4.79 4.79 0 0 1 2.02.39c.62.26 1.14.7 1.55 1.32.34.45.58.99.74 1.61.16.63.24 1.27.23 1.92 0 .84-.1 1.61-.31 2.31-.18.66-.53 1.27-1.01 1.76-.41.43-.9.76-1.46.97-.54.2-1.11.31-1.69.31a5.32 5.32 0 0 1-1.86-.33c-.57-.23-1.09-.63-1.55-1.18v1.36h-2.74V5.59h2.74v4.8h0zm0 3.84c.01.83.25 1.5.7 2.02.47.52 1.11.78 1.92.79.44.01.82-.05 1.13-.19s.56-.34.76-.6a2.91 2.91 0 0 0 .47-.93c.12-.35.17-.73.17-1.14a3.65 3.65 0 0 0-.17-1.12 2.52 2.52 0 0 0-.49-.91c-.21-.26-.47-.46-.78-.6s-.69-.21-1.13-.21c-.41 0-.79.08-1.13.23a2.46 2.46 0 0 0-.82.64c-.21.26-.37.57-.49.93a4.4 4.4 0 0 0-.14 1.09z" fill="url(#G)"/><linearGradient id="H" gradientUnits="userSpaceOnUse" x1="26.035" y1="3.852" x2="127.827" y2="18.338"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M97.22 17.04c.75 0 1.28-.19 1.59-.58.31-.4.45-.97.43-1.71V9.1h2.74v5.74c0 .71-.07 1.32-.21 1.82-.13.5-.36.97-.68 1.38-.25.31-.52.55-.82.72a5.58 5.58 0 0 1-.93.43 5.42 5.42 0 0 1-1.05.21c-.35.05-.71.08-1.07.08-.8 0-1.53-.11-2.19-.33-.64-.21-1.21-.59-1.65-1.1a3.54 3.54 0 0 1-.7-1.38c-.13-.5-.19-1.11-.19-1.82V9.1h2.74v5.66c-.01.74.14 1.3.47 1.69.31.38.82.58 1.52.59h0z" fill="url(#H)"/><linearGradient id="I" gradientUnits="userSpaceOnUse" x1="26.546" y1=".66" x2="128.268" y2="15.136"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M105.63 4.35c.51 0 .91.15 1.22.45s.47.69.47 1.18c0 .52-.16.92-.47 1.22-.31.28-.72.43-1.22.43-.49 0-.9-.14-1.22-.43-.31-.3-.47-.7-.47-1.22 0-.49.16-.88.47-1.18.32-.3.72-.45 1.22-.45zm1.38 14.96h-2.74V9.1h2.74v10.21z" fill="url(#I)"/><linearGradient id="J" gradientUnits="userSpaceOnUse" x1="26.582" y1=".479" x2="128.298" y2="14.954"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M112.07,19.31h-2.74V5.59h2.74V19.31z" fill="url(#J)"/><linearGradient id="K" gradientUnits="userSpaceOnUse" x1="26.715" y1="-.929" x2="128.511" y2="13.558"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#fff" stop-opacity=".48"/></linearGradient><path d="M121.76 18.17c-.39.42-.87.75-1.4.97-.51.19-1.12.3-1.85.33-.67 0-1.29-.1-1.86-.31a4.05 4.05 0 0 1-1.48-.97c-.49-.51-.85-1.13-1.05-1.8a7.81 7.81 0 0 1-.29-2.09c0-1.67.47-3 1.4-4.01.41-.44.93-.78 1.55-1.03s1.33-.37 2.12-.37c.6 0 1.13.09 1.59.27.48.17.88.42 1.2.76V5.59h2.76v13.72h-2.54l-.15-1.14zm-2.6-1.13c.83-.03 1.46-.3 1.88-.83.44-.54.66-1.21.66-2 0-.85-.22-1.54-.66-2.07s-1.08-.8-1.92-.81c-.43 0-.8.07-1.13.21-.31.14-.58.35-.8.62a2.71 2.71 0 0 0-.47.91c-.11.36-.16.73-.16 1.1 0 .43.05.81.16 1.16.1.34.26.63.47.89.22.26.49.46.82.6.34.15.72.22 1.15.22z" fill="url(#K)"/><path d="M12.09.18L.25 7.08l11.84 6.9 11.84-6.9L12.09.18z" fill="#6f4099"/><path d="M12.07 1.32L8.8 3.23v7.68l3.29 1.92 3.29-1.92V7.08l3.29-1.92c-.01.01-6.6-3.84-6.6-3.84zm-2.04 4.54a.69.69 0 1 1 0-1.38.69.69 0 1 1 0 1.38zm4.79.9l-2.76-1.58 2.76-1.61 2.74 1.6-2.74 1.59h0z" fill="#fff"/><linearGradient id="L" gradientUnits="userSpaceOnUse" x1="6.057" y1="7.274" x2="6.057" y2="27.97"><stop offset="0" stop-color="#9a4198"/><stop offset="1" stop-color="#3c1e56"/><stop offset="1" stop-color="#231815"/></linearGradient><path d="M.14 7.27v13.8l11.84 6.9v-13.8L.14 7.27z" fill="url(#L)"/><path d="M10.44 25.29l-2.19-1.28v-.64l-4.39-7.66-.54-.32v5.74l-1.65-.95V9.96l2.19 1.27v.64l4.39 7.67.55.32v-5.75l1.64.96z" fill="#fff"/><path d="M12.19 14.17v13.8l11.84-6.9V7.27l-11.84 6.9h0z" fill="#3c1e56"/><path d="M22.5 11.87l-1.64-.96-7.12 4.15v10.22l7.67-4.47 1.1-1.92v-2.55l-1.1-.64 1.1-1.92-.01-1.91h0zm-1.65 7.03l-.82 1.44-4.66 2.71v-3.19l4.66-2.71.82.48v1.27h0zm0-4.47l-.82 1.44-4.66 2.71v-3.19l4.66-2.71.82.48v1.27h0z" fill="#fff"/></svg>
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
    <a href="/plans/" class="nav_button">
      <span>
        <?= $svg_plans_icon ?>
      </span>
      Plans
    </a>
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
    <a class="ref_link" style="font-size: large" href="https://nostr.build/plans/"><img src="https://i.nostr.build/0Q82.png" style="width:220px;" alt="Premium Accounts"></a>
  <?php } ?>
</nav>