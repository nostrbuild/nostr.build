#!/bin/bash

# Install dependencies
brew install cmake make pkg-config x265 libde265 libjpeg libtool

# Clone the libheif repository
git clone https://github.com/strukturag/libheif.git

# Go to the libheif directory
cd libheif

# Configure and build the project
mkdir build
cd build
cmake --preset=release ..
make

# Go back to the initial directory
cd ../..
