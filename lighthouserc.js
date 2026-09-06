/**
 * Lighthouse CI — this is the gate.
 *
 * total-blocking-time stands in for INP, which has no lab equivalent.
 * Byte thresholds are transfer size and assume gzip is on in the test server.
 * SEED_ID is replaced at CI time by tools/seed/lhci-url.mjs with a real
 * product post ID from the restored snapshot.
 */
module.exports = {
  ci: {
    collect: {
      url: [
        'http://localhost:8889/shop/',
        'http://localhost:8889/?post_type=product&p=SEED_ID',
      ],
      numberOfRuns: 3,
      settings: { formFactor: 'mobile', throttlingMethod: 'simulate' },
    },
    assert: {
      assertions: {
        'categories:performance': [ 'error', { minScore: 0.95 } ],
        'largest-contentful-paint': [ 'error', { maxNumericValue: 1800 } ],
        'cumulative-layout-shift': [ 'error', { maxNumericValue: 0.05 } ],
        'total-blocking-time': [ 'error', { maxNumericValue: 200 } ],
        'resource-summary:stylesheet:size': [ 'error', { maxNumericValue: 61440 } ],
        'resource-summary:script:size': [ 'error', { maxNumericValue: 40960 } ],
        'render-blocking-resources': [ 'error', { maxLength: 0 } ],
        'uses-responsive-images': [ 'warn', { maxLength: 0 } ],
      },
    },
    upload: { target: 'temporary-public-storage' },
  },
};
