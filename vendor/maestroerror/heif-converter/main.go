package main

import (
	"fmt"
	"os"

	"github.com/MaestroError/go-libheif"
)

func main() {
	if len(os.Args) < 4 {
		fmt.Printf("Usage: %s [heic|avif|jpeg|png] input_file output_file\n", os.Args[0])
		Shutdown()
	}

	inputFormat := os.Args[1]
	inputFile := os.Args[2]
	outputFile := os.Args[3]

	switch inputFormat {
	case "heic", "avif":
		// Determine the output format based on the output file extension
		switch outputFile[len(outputFile)-3:] {
		case "jpg", "peg":
			err := libheif.HeifToJpeg(inputFile, outputFile, 100)
			if err != nil {
				fmt.Println(err)
			}
		case "png":
			err := libheif.HeifToPng(inputFile, outputFile)
			if err != nil {
				fmt.Println(err)
			}
		default:
			fmt.Println("Unsupported output format")
		}
	case "jpg", "peg", "jpeg", "png":
		err := libheif.ImageToHeif(inputFile, outputFile)
		if err != nil {
			fmt.Println(err)
		}
	default:
		fmt.Println("Unsupported input format")
	}
}

func Shutdown() {
	fmt.Println("Created by MaestroError")
	os.Exit(1)
}
