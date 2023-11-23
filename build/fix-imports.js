const fs = require('fs');
const path = require('path');

const directoryPath = path.join(__dirname, 'node_modules/@uppy/provider-views/lib/ProviderView');

fs.readdir(directoryPath, function (err, files) {
  if (err) {
    return console.log('Unable to scan directory: ' + err);
  }

  files.forEach(function (file) {
    let filePath = path.join(directoryPath, file);
    let content = fs.readFileSync(filePath, 'utf8');
    let modifiedContent = content.replace("from 'p-queue'", "from 'p-queue/dist'");
    fs.writeFileSync(filePath, modifiedContent);
  });
});

