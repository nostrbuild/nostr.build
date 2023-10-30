#!/bin/bash

# Update system packages
sudo apt-get update -y
# Install necessary tools and libraries for building libheif
sudo apt-get install -y git cmake make pkg-config libx265-dev libde265-dev libjpeg-dev libtool

# Clone libheif
git clone https://github.com/strukturag/libheif.git

# Navigate into the libheif directory
cd libheif

# Create a new directory for the build and navigate into it
mkdir build
cd build

# Generate the Makefile with cmake
cmake ..

# Compile the project
make

# Install the compiled library
sudo make install

# Update library cache
sudo ldconfig

echo "Installation of libheif is completed."
