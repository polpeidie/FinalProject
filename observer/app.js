const chokidar = require('chokidar')
const fs = require('fs')
const http = require('http')

const filePath = 'mock.json'

// Initialize watcher
const watcher = chokidar.watch(filePath)

function sendDataToPHP(data) {
    const postData = JSON.stringify({ data });

    const options = {
        hostname: '192.168.233.171', // Replace with your server hostname
        port: 8080, // Replace with your server port
        path: '/test.php',
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Content-Length': postData.length
        }
    };

    const req = http.request(options, (res) => {
        console.log(`statusCode: ${res.statusCode}`);

        res.on('', (d) => {
            process.stdout.write(d);
        });
    });

    req.on('error', (error) => {
        console.error(error);
    });

    req.write(postData);
    req.end();
}

// Add event listeners
watcher.on('change', path => {
    console.log(`File ${path} has been changed`)

    // Read the updated JSON file
    fs.readFile(path, 'utf-8', (err, data) => {
        if (err) {
            console.error('Error reading file:', err)
            return
        }

        const jsonData = JSON.parse(data)

        // Process JSON data as needed
        console.log(jsonData)
        console.log('\n')

        // Send data to PHP file
        sendDataToPHP(jsonData);
    })
})

watcher.on('add', path => {
    console.log('\nThe app is now listening for changes...\n')

    console.log(`File ${path} has been added`)

    // Read the updated JSON file
    fs.readFile(path, 'utf-8', (err, data) => {
        if (err) {
            console.error('Error reading file:', err)
            return
        }

        const jsonData = JSON.parse(data)

        // Process JSON data as needed
        console.log(jsonData)
        console.log('\n')

        // Send data to PHP file
        sendDataToPHP(jsonData);
    })
})