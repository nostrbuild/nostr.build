@echo off

REM Clone the vcpkg repository
git clone https://github.com/Microsoft/vcpkg.git

REM Enter the vcpkg directory
cd vcpkg

REM Bootstrap vcpkg
.\bootstrap-vcpkg.bat

REM Install libheif
.\vcpkg integrate install
.\vcpkg install libheif

REM Define PKG_CONFIG_PATH
setx PKG_CONFIG_PATH "%cd%\installed\x64-windows\lib\pkgconfig;%cd%\installed\x86-windows\lib\pkgconfig"

REM Add the bin directory to the PATH
setx PATH "%PATH%;%cd%\installed\x64-windows\bin;%cd%\installed\x86-windows\bin"

REM Go back to the initial directory
cd ..
