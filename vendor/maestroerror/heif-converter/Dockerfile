# Start from a base image that already has Go installed
FROM golang:1.18

# Set the Current Working Directory inside the container
WORKDIR /app

# Copy everything from the current directory to the Working Directory inside the container
COPY . .

# Install libheif
RUN apt-get update && apt-get install -y git cmake make pkg-config libx265-dev libde265-dev libjpeg-dev libtool

RUN git clone https://github.com/strukturag/libheif.git && \
    cd libheif && \
    mkdir build && cd build && \
    cmake .. && \
    make && \
    make install

# # Install brew
# RUN /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
# ENV PATH="/home/linuxbrew/.linuxbrew/bin:${PATH}"

# # Install deps and libheif with brew
# RUN brew install cmake make pkg-config x265 libde265 libjpeg libtool
# RUN brew install libheif

# Update the LD_LIBRARY_PATH
ENV LD_LIBRARY_PATH=/usr/local/lib:$LD_LIBRARY_PATH

# Build the Go app
RUN go build -o converter .

# The ENTRYPOINT specifies a command that will always be executed when the container starts. 
# In this case, it will run your CLI tool.
ENTRYPOINT ["./converter"]
