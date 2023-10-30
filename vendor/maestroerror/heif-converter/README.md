# heif-converter-image
heif-converter is a versatile command-line application, along with a Docker image, that offers an easy and efficient way to convert HEIC (and AVIF) images to other common formats like JPEG and PNG, and vice versa. It leverages the go-libheif module, a GoLang wrapper for the libheif library. This tool can be used as a standalone CLI application across various platforms including macOS, Linux, and Windows, or used via a Docker container, making it a flexible solution for all your HEIC image conversion needs.

## Installing dependencies
heif-converter relies on [libheif](https://github.com/strukturag/libheif), which must be installed on your system. To simplify the installation process, I've provided ready-to-use scripts for different operating systems::
- install-libheif.sh
- install-libheif-macos.sh
- install-libheif-windows.bat
           
*note: these scripts assume that the required tools (such as **git** and **brew** for macOS or **git** and **Visual Studio with C++ Desktop development** for Windows) are already installed on the system.*

## Using from docker
In case you find hard to install libheif, you can use docker. Just Pull [docker image](https://hub.docker.com/r/maestroerror/heif-converter):
```bash
docker pull maestroerror/heif-converter:latest
```
To run the converter and convert an image:

```bash

docker run --rm -v /path/to/your/files:/app/files maestroerror/heif-converter [heic|avif|jpeg|png] /app/files/input_file /app/files/output_file
```

*Note: replace /path/to/your/files with the path to the directory containing the images you want to convert. The converter will look for the input file in this directory and will also write the output file to this directory.*

### Usage via Composer

You can also use heif-converter via Composer, a dependency manager for PHP. First, you need to add heif-converter to your project's dependencies. Navigate to your project's root directory in your terminal and run the following command:

```bash
composer require maestroerror/heif-converter
```

After installing the heif-converter, you can find the executable in the vendor/bin directory. Depending on your operating system, use the appropriate version:

- Linux: `./vendor/bin/heif-converter-linux`
- Windows: `./vendor/bin/heif-converter-windows`
- MacOS: `./vendor/bin/heif-converter-macos`

For example, to convert an image on a Linux system, you can run:

```bash
./vendor/bin/heif-converter-linux heic input.heic output.png
```

#### Running Installation Script

Before using the heif-converter, you may need to run the installation script for your platform. These scripts install the necessary dependencies (libheif).
            
For Linux, run install-libheif.sh:
```bash
./vendor/maestroerror/heif-converter/install-libheif.sh
```
For MacOS, run install-libheif-macos.sh:
```bash
./vendor/maestroerror/heif-converter/install-libheif-macos.sh
```
For Windows, you need to use the command prompt to run install-libheif-windows.bat:
```bash
.\vendor\maestroerror\heif-converter\install-libheif-windows.bat
```
After running the appropriate script, you should be able to use the heif-converter command as explained in next chapter. To ensure your installation is ready to use run `./vendor/bin/heif-converter-linux` command without arguments, you should get output like this:
```bash
Usage: ./vendor/bin/heif-converter-linux [heic|avif|jpeg|png] input_file output_file
``` 

## Usage
Just point to the executable (`./heif-converter`), add the current image format (`[heic|avif|jpeg|png]`) as the first argument and input / output files as the second and third arguments.
```bash
./heif-converter [heic|avif|jpeg|png] path/to/input_file.heic /path/to/output_file.png
```
*Note: It will detect output file format automatically based on the extension*         
App ships with 3 binary file in the bin directory. Choose by your platform:
- heif-converter-linux
- heif-converter-macos
- heif-converter-windows.exe

## Contributions

We warmly welcome all contributions to the heif-converter-image project! This project is completely open source and relies on contributions from the community to expand its reach, improve its functionality, and fix any existing issues.

#### How to Contribute

If you are interested in contributing to the heif-converter-image project, you are free to submit Pull Requests and Issues. Your contributions can range from bug fixes, feature enhancements, adding documentation, to writing test cases. Before submitting a Pull Request, please ensure that you have tested your changes thoroughly and that all tests pass.
        
In your Pull Requests, be as detailed as possible in your commit messages and comments to help the maintainers understand your changes. For Issues, provide as much detail as you can about the problem, including steps to reproduce it, your operating system and version, etc.
Building for Different Platforms
        
Currently, heif-converter-image is shipped with executables for Windows, Linux, and macOS Intel-based architectures. However, we understand the growing demand and necessity for supporting a broader range of platforms.
        
We're particularly interested in expanding support for Linux ARM64 and macOS M1 architectures. If you have experience in building for these platforms or have these systems for testing, your contributions would be greatly appreciated.
        
Remember, open source projects thrive on the collaboration and contributions from developers like you. Together, we can make heif-converter-image even better. Let's build something amazing together!
        
### Development
- build test bin `go build -o bin/heif-converter`