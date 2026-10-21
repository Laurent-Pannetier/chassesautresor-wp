/**
 * Maps the hunt image onto a sphere for the home banner.
 * Rebuild the footer bundle with:
 * npx esbuild assets/js/src/hunt-banner-sphere.js --bundle --format=iife --minify
 *   --target=es2018 --legal-comments=inline --outfile=assets/js/hunt-banner-sphere.js
 */
import {
  AmbientLight,
  DirectionalLight,
  Mesh,
  MeshStandardMaterial,
  PerspectiveCamera,
  Quaternion,
  Scene,
  SphereGeometry,
  SRGBColorSpace,
  TextureLoader,
  WebGLRenderer,
} from 'three';

var reduceMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
var turnSpeed = 0.0034;

function randomQuaternion() {
  var u1 = Math.random();
  var u2 = Math.random();
  var u3 = Math.random();
  var twoPi = Math.PI * 2;

  return new Quaternion(
    Math.sqrt(1 - u1) * Math.sin(twoPi * u2),
    Math.sqrt(1 - u1) * Math.cos(twoPi * u2),
    Math.sqrt(u1) * Math.sin(twoPi * u3),
    Math.sqrt(u1) * Math.cos(twoPi * u3)
  );
}

function pickTurn(from) {
  var next = new Quaternion();
  var attempt = 0;

  do {
    next.copy(randomQuaternion());
    attempt += 1;
  } while (from.angleTo(next) < 1.15 && attempt < 8);

  return next;
}

function mount(root) {
  var url = root.getAttribute('data-image') || '';
  var canvas = root.querySelector('.hunt-banner__canvas');
  var fallback = root.querySelector('.hunt-banner__fallback');
  if (!url || !canvas) {
    root.classList.remove('is-pending');
    root.classList.add('is-fallback');
    return;
  }

  var renderer;
  try {
    renderer = new WebGLRenderer({
      canvas: canvas,
      alpha: true,
      antialias: true,
      powerPreference: 'low-power',
    });
  } catch (error) {
    root.classList.remove('is-pending');
    root.classList.add('is-fallback');
    return;
  }

  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.outputColorSpace = SRGBColorSpace;
  renderer.setClearColor(0x000000, 0);

  var scene = new Scene();
  var camera = new PerspectiveCamera(32, 1, 0.1, 20);

  scene.add(new AmbientLight(0xffffff, 0.62));
  var key = new DirectionalLight(0xffffff, 1.35);
  key.position.set(-2.4, 1.8, 3.2);
  scene.add(key);
  var fill = new DirectionalLight(0xb7d4e8, 0.38);
  fill.position.set(2.4, -0.6, 1.4);
  scene.add(fill);

  var material = new MeshStandardMaterial({
    color: 0xffffff,
    roughness: 0.76,
    metalness: 0.02,
  });
  var mesh = new Mesh(new SphereGeometry(1, 64, 48), material);
  scene.add(mesh);
  var turnTarget = pickTurn(mesh.quaternion);

  var running = true;
  var frameId = 0;
  var observer = null;

  function resize() {
    var width = root.clientWidth || 1;
    var height = root.clientHeight || 1;
    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    var halfFov = (camera.fov * Math.PI) / 360;
    camera.position.z = 2.4 / (2 * Math.tan(halfFov));
    camera.updateProjectionMatrix();
  }

  function stop() {
    running = false;
    if (frameId) {
      window.cancelAnimationFrame(frameId);
      frameId = 0;
    }
  }

  function frame() {
    if (!running) {
      return;
    }
    if (!reduceMotionQuery.matches) {
      var remaining = mesh.quaternion.angleTo(turnTarget);
      if (remaining <= turnSpeed) {
        mesh.quaternion.copy(turnTarget);
        turnTarget = pickTurn(mesh.quaternion);
      } else {
        mesh.quaternion.slerp(turnTarget, turnSpeed / remaining);
      }
    }
    renderer.render(scene, camera);
    frameId = window.requestAnimationFrame(frame);
  }

  function fail() {
    stop();
    if (observer) {
      observer.disconnect();
    }
    material.dispose();
    mesh.geometry.dispose();
    renderer.dispose();
    root.classList.remove('is-ready');
    root.classList.remove('is-pending');
    root.classList.add('is-fallback');
    if (fallback) {
      fallback.removeAttribute('aria-hidden');
    }
  }

  resize();
  frame();

  if (typeof ResizeObserver === 'function') {
    observer = new ResizeObserver(resize);
    observer.observe(root);
  } else {
    window.addEventListener('resize', resize);
  }

  document.addEventListener('visibilitychange', function () {
    if (root.classList.contains('is-fallback')) {
      return;
    }
    if (document.hidden) {
      stop();
      return;
    }
    if (!running) {
      running = true;
      frame();
    }
  });

  var loader = new TextureLoader();
  loader.setCrossOrigin('anonymous');
  loader.load(url, function (texture) {
    if (root.classList.contains('is-fallback')) {
      texture.dispose();
      return;
    }
    texture.colorSpace = SRGBColorSpace;
    texture.anisotropy = Math.min(8, renderer.capabilities.getMaxAnisotropy());
    material.map = texture;
    material.needsUpdate = true;
    root.classList.add('is-ready');
    if (fallback) {
      fallback.setAttribute('aria-hidden', 'true');
    }
  }, undefined, fail);
}

function boot() {
  document.querySelectorAll('[data-hunt-sphere]').forEach(mount);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
