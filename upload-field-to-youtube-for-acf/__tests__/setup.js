/**
 * Jest setup file for Upload Field to YouTube for ACF
 * Configures global mocks and test environment
 */

// Mock global WordPress objects
global.wp = {
  data: {
    select: jest.fn(() => ({
      getEditedPostAttribute: jest.fn(() => 'Test Title')
    }))
  }
};

// Mock WordPress AJAX URL
global.ajaxurl = '/wp-admin/admin-ajax.php';

// Mock File API for older browsers if needed
if (typeof global.File === 'undefined') {
  global.File = class File {
    constructor(bits, name, options = {}) {
      this.bits = bits;
      this.name = name;
      this.type = options.type || '';
      this.size = bits.reduce((acc, bit) => acc + bit.length, 0);
    }
  };
}

// Mock FormData if needed
if (typeof global.FormData === 'undefined') {
  global.FormData = class FormData {
    constructor() {
      this.data = new Map();
    }
    
    append(key, value) {
      this.data.set(key, value);
    }
    
    get(key) {
      return this.data.get(key);
    }
  };
}

// Mock fetch globally
global.fetch = jest.fn(() =>
  Promise.resolve({
    json: () => Promise.resolve({ success: true, data: { message: 'Test success' } }),
    text: () => Promise.resolve('Test response'),
    headers: new Map([['content-type', 'application/json']])
  })
);

// Console setup for cleaner test output
const originalError = console.error;
beforeAll(() => {
  console.error = (...args) => {
    if (typeof args[0] === 'string' && args[0].includes('Warning: React.createElement')) {
      return;
    }
    originalError.call(console, ...args);
  };
});

afterAll(() => {
  console.error = originalError;
});

// Clean up after each test
afterEach(() => {
  jest.clearAllMocks();
  document.body.innerHTML = '';
});