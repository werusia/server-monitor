import { defineConfig } from 'cypress';
import { readFileSync } from 'fs';
import { resolve } from 'path';

// Helper function to read .env.local file
function loadEnvFile() {
  try {
    const envPath = resolve(process.cwd(), '.env.local');
    const envContent = readFileSync(envPath, 'utf8');
    const envVars = {};
    
    envContent.split('\n').forEach((line) => {
      const trimmedLine = line.trim();
      if (trimmedLine && !trimmedLine.startsWith('#')) {
        const [key, ...valueParts] = trimmedLine.split('=');
        if (key && valueParts.length > 0) {
          envVars[key.trim()] = valueParts.join('=').trim().replace(/^["']|["']$/g, '');
        }
      }
    });
    
    return envVars;
  } catch (error) {
    // .env.local doesn't exist or can't be read, return empty object
    return {};
  }
}

export default defineConfig({
  e2e: {
    baseUrl: 'https://server-monitor.ddev.site:33003',
    supportFile: 'cypress/support/e2e.js',
    specPattern: 'cypress/e2e/**/*.cy.{js,jsx,ts,tsx}',
    fixturesFolder: 'cypress/fixtures',
    screenshotsFolder: 'cypress/screenshots',
    videosFolder: 'cypress/videos',
    downloadsFolder: 'cypress/downloads',
    viewportWidth: 1280,
    viewportHeight: 720,
    video: true,
    screenshotOnRunFailure: true,
    defaultCommandTimeout: 10000,
    requestTimeout: 10000,
    responseTimeout: 10000,
    pageLoadTimeout: 30000,
    watchForFileChanges: true,
    // Disable web security for DDEV self-signed certificates
    chromeWebSecurity: false,
    setupNodeEvents(on, config) {
      // Load environment variables from .env.local or process.env
      const envVars = loadEnvFile();
      
      // Set Cypress environment variables
      // Priority: process.env > .env.local > cypress.env.json
      config.env.APP_PASSWORD = 
        process.env.APP_PASSWORD || 
        process.env.DASHBOARD_PASSWORD || 
        envVars.APP_PASSWORD || 
        envVars.DASHBOARD_PASSWORD || 
        config.env.APP_PASSWORD;
      
      return config;
    },
  },
  component: {
    devServer: {
      framework: 'create-react-app',
      bundler: 'webpack',
    },
  },
});

