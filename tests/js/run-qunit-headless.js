const puppeteer = require('puppeteer');
const path = require('path');
const fs = require('fs');

// Path to the QUnit HTML file
const qunitHtmlPath = path.join(__dirname, 'qunit-tests.html');

// Check if the QUnit HTML file exists
if (!fs.existsSync(qunitHtmlPath)) {
  console.error(`Error: QUnit HTML file not found at ${qunitHtmlPath}`);
  console.error('Please ensure the QUnit tests are set up correctly and the HTML runner exists.');
  process.exit(1);
}

(async () => {
  let browser;
  try {
    console.log('Launching Puppeteer...');
    browser = await puppeteer.launch({
      headless: true,
      args: [
        '--no-sandbox', // Required for running in some CI environments
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage', // Recommended for Docker/CI
      ],
    });
    const page = await browser.newPage();

    // Enable console logging from the page
    page.on('console', msg => {
      const type = msg.type();
      const text = msg.text();
      // Filter out QUnit's own logging to avoid duplication, unless it's an error
      if (type === 'error' || (type === 'log' && !text.startsWith('QUnit.'))) {
        console.log(`[Browser Console] ${type.toUpperCase()}: ${text}`);
      }
    });
    page.on('pageerror', (error) => {
        console.error(`[Page Error]: ${error.message}`);
    });

    console.log(`Navigating to QUnit test page: file://${qunitHtmlPath}`);
    await page.goto(`file://${qunitHtmlPath}`, { waitUntil: 'networkidle0' });

    console.log('Waiting for QUnit tests to complete...');

    // Wait for the QUnit results element to appear, indicating tests are done
    // and QUnit has rendered its results summary.
    await page.waitForSelector('#qunit-testresult', { timeout: 60000 }); // 60 seconds timeout

    // Extract QUnit test results
    const results = await page.evaluate(() => {
      const resultElement = document.getElementById('qunit-testresult');
      if (!resultElement) {
        return {
          total: 0,
          passed: 0,
          failed: 0,
          runtime: 0,
          message: 'Could not find QUnit results element (#qunit-testresult).',
        };
      }

      const message = resultElement.innerText.trim();
      let passed = 0;
      let total = 0;
      let failed = 0;

      const passedEl = resultElement.querySelector('.passed');
      if (passedEl) passed = parseInt(passedEl.innerText, 10) || 0;

      const totalEl = resultElement.querySelector('.total');
      if (totalEl) total = parseInt(totalEl.innerText, 10) || 0;

      // Infer failed from total and passed if not directly available
      // Sometimes QUnit might not have a '.failed' element if all pass
      failed = total - passed;

      // Attempt to get the failed count directly if available, this is more robust
      const failedEl = resultElement.querySelector('.failed');
      if (failedEl) failed = parseInt(failedEl.innerText, 10) || 0;


      // Extract runtime - this might need adjustment based on QUnit version
      let runtime = 0;
      const runtimeMatch = message.match(/finished in (\d+)ms/i);
      if (runtimeMatch && runtimeMatch[1]) {
        runtime = parseInt(runtimeMatch[1], 10);
      }

      return {
        total,
        passed,
        failed,
        runtime,
        message,
      };
    });

    console.log('\n--- QUnit Test Results ---');
    console.log(results.message);
    console.log('--------------------------\n');

    if (results.failed > 0) {
      console.error(`🔴 Tests failed: ${results.failed} of ${results.total}`);
      process.exitCode = 1;
    } else if (results.total === 0 && !results.message.includes('0 tests completed')) {
      // If no tests were found, but the message doesn't explicitly say "0 tests"
      // (which can happen if QUnit itself fails to load tests), treat as an error.
      console.error('⚠️ No tests were executed or QUnit results were not properly reported.');
      process.exitCode = 1;
    }
    else if (results.total > 0 && results.failed === 0) {
      console.log(`✅ All ${results.passed} tests passed!`);
    } else {
      console.log('ℹ️ No tests found or executed.');
    }

  } catch (error) {
    console.error('Error running Puppeteer tests:', error);
    process.exitCode = 1; // Ensure failure exit code
  } finally {
    if (browser) {
      console.log('Closing Puppeteer...');
      await browser.close();
    }
    // Ensure process exits if not already set by failure
    if (process.exitCode === undefined) {
        process.exitCode = 0;
    }
    process.exit(process.exitCode);
  }
})();
