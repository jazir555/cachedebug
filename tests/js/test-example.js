QUnit.module('Example Tests', function() {
  QUnit.test('A basic true is true test', function(assert) {
    assert.strictEqual(true, true, 'True should be true');
  });

  QUnit.test('Another basic test: addition', function(assert) {
    assert.equal(2 + 2, 4, '2 + 2 should equal 4');
  });
});
