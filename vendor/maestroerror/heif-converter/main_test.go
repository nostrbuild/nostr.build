package main

import (
	"io/ioutil"
	"os"
	"os/exec"
	"testing"

	"github.com/stretchr/testify/assert"
)

// TestMainFunction tests the main function
func TestMainFunction(t *testing.T) {
	// Create a temporary directory
	tmpDir, err := ioutil.TempDir("tmp", "")
	if err != nil {
		t.Fatal(err)
	}
	defer os.RemoveAll(tmpDir)

	// Copy an example HEIC file and an example JPG file to the temporary directory
	// (You need to replace `path/to/example.heic` and `path/to/example.jpg` with actual paths.)
	heicFile := tmpDir + "/example.heic"
	err = exec.Command("cp", "images/samsung-generated.heic", heicFile).Run()
	if err != nil {
		t.Fatal(err)
	}
	jpgFile := tmpDir + "/example.jpg"
	err = exec.Command("cp", "images/jpg-from-heic.jpg", jpgFile).Run()
	if err != nil {
		t.Fatal(err)
	}

	// Run the main function with the HEIC file as input and a new JPG file as output
	err = exec.Command("go", "run", "main.go", "heic", heicFile, tmpDir+"/output.jpg").Run()
	assert.NoError(t, err, "Converting from HEIC to JPG should not produce an error")

	// Run the main function with the JPG file as input and a new HEIC file as output
	err = exec.Command("go", "run", "main.go", "jpeg", jpgFile, tmpDir+"/output.heic").Run()
	assert.NoError(t, err, "Converting from JPG to HEIC should not produce an error")
}
