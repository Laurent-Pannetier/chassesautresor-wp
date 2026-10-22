/**
 * Lateral roll for the home-banner sphere.
 * The center stops where the silhouette meets the frame, so the circumference
 * touches the edge. A side rebound speeds the sphere up and picks a new spin
 * axis, so a different part of the surface rolls into view. The roll then
 * eases down until the opposite edge.
 */
var RADIUS = 1;
var BASE_SPEED = 0.32;
var MIN_SPEED = 0.12;
var PEAK_SPEED = 0.84;
var KICK_MIN = 0.18;
var KICK_MAX = 0.56;
var ARRIVAL_RATIO_MIN = 0.28;
var ARRIVAL_RATIO_MAX = 0.56;
var TUMBLE_MIN = 0.85;
var TUMBLE_MAX = 1.9;
var CONTACT_MARGIN = 0.012;

function silhouetteAngle(x, cameraZ) {
  var distance = Math.hypot(x, cameraZ);
  if (!(distance > RADIUS)) {
    return Math.PI;
  }
  return Math.atan2(Math.abs(x), cameraZ) + Math.asin(RADIUS / distance);
}

/**
 * Largest |x| whose circumference stays inside the frame.
 * `halfWidth` is the visible half-width on the plane z = 0.
 */
function travelLimit(halfWidth, cameraZ) {
  if (!(halfWidth > 0) || !(cameraZ > RADIUS)) {
    return 0;
  }
  var frame = Math.atan(halfWidth / cameraZ);
  if (!(frame > silhouetteAngle(0, cameraZ) + CONTACT_MARGIN)) {
    return 0;
  }

  var low = 0;
  var high = halfWidth;
  var step;
  for (step = 0; step < 28; step += 1) {
    var mid = (low + high) / 2;
    if (silhouetteAngle(mid, cameraZ) + CONTACT_MARGIN <= frame) {
      low = mid;
    } else {
      high = mid;
    }
  }
  return low > 0.02 ? low : 0;
}

/**
 * Outgoing speed is faster than the speed at impact. The next edge is slower.
 * `random` is read twice: kick, then arrival ratio.
 */
function pickBounceSpeeds(incoming, random) {
  var speedIn = incoming > 0 ? incoming : MIN_SPEED;
  var kick = KICK_MIN + random() * (KICK_MAX - KICK_MIN);
  var exitSpeed = speedIn + kick;
  if (exitSpeed > PEAK_SPEED) {
    exitSpeed = PEAK_SPEED;
  }
  var minimumExit = speedIn + KICK_MIN;
  if (minimumExit > PEAK_SPEED) {
    minimumExit = PEAK_SPEED;
  }
  if (exitSpeed < minimumExit) {
    exitSpeed = minimumExit;
  }

  var ratio = ARRIVAL_RATIO_MIN + random() * (ARRIVAL_RATIO_MAX - ARRIVAL_RATIO_MIN);
  var arrivalSpeed = exitSpeed * ratio;
  if (arrivalSpeed < MIN_SPEED) {
    arrivalSpeed = MIN_SPEED;
  }
  var slowest = exitSpeed - 0.1;
  if (slowest < MIN_SPEED) {
    slowest = MIN_SPEED;
  }
  if (arrivalSpeed > slowest) {
    arrivalSpeed = slowest;
  }
  return {
    exitSpeed: exitSpeed,
    arrivalSpeed: arrivalSpeed,
  };
}

/**
 * Spin, in radians per unit traveled, around an axis in the screen plane.
 * `random` is read twice: rate, then direction.
 */
function pickTumble(random) {
  var rate = TUMBLE_MIN + random() * (TUMBLE_MAX - TUMBLE_MIN);
  var phi = random() * Math.PI * 2;
  return {
    x: Math.cos(phi) * rate,
    y: Math.sin(phi) * rate,
  };
}

function createRollState(limit, random) {
  var direction = random() < 0.5 ? -1 : 1;
  var speeds = pickBounceSpeeds(0.22, random);
  var tumble = pickTumble(random);
  var speed = (speeds.exitSpeed + speeds.arrivalSpeed) / 2;
  return {
    x: 0,
    velocityX: direction * speed,
    limit: limit,
    exitSpeed: speeds.exitSpeed,
    arrivalSpeed: speeds.arrivalSpeed,
    tumbleX: tumble.x,
    tumbleY: tumble.y,
  };
}

function readSpeeds(state, velocity) {
  var fallback = Math.abs(velocity);
  if (!(fallback > 0)) {
    fallback = BASE_SPEED;
  }
  var exitSpeed = state.exitSpeed > 0 ? state.exitSpeed : fallback;
  var arrivalSpeed = state.arrivalSpeed > 0 ? state.arrivalSpeed : exitSpeed;
  return {
    exitSpeed: exitSpeed,
    arrivalSpeed: arrivalSpeed,
  };
}

function distanceForTime(speed, slope, time) {
  if (!(time > 0) || !(speed > 0)) {
    return 0;
  }
  if (Math.abs(slope) < 0.00000001) {
    return speed * time;
  }
  return (speed * Math.exp(slope * time) - speed) / slope;
}

function timeForDistance(speed, slope, distance) {
  if (!(distance > 0) || !(speed > 0)) {
    return 0;
  }
  if (Math.abs(slope) < 0.00000001) {
    return distance / speed;
  }
  var next = speed + slope * distance;
  if (!(next > 0.000001)) {
    return distance / speed;
  }
  return Math.log(next / speed) / slope;
}

function pushSpin(spins, distance, tumbleX, tumbleY) {
  if (distance === 0) {
    return;
  }
  var rx = tumbleX * distance;
  var ry = tumbleY * distance;
  var rz = -distance / RADIUS;
  var angle = Math.hypot(rx, ry, rz);
  if (!(angle > 0)) {
    return;
  }
  spins.push({
    angle: angle,
    x: rx / angle,
    y: ry / angle,
    z: rz / angle,
  });
}

function finishedStep(x, velocity, traveled, bounced, speeds, tumbleX, tumbleY, spins) {
  return {
    x: x,
    velocityX: velocity,
    rollAngle: traveled === 0 ? 0 : -traveled / RADIUS,
    bounced: bounced,
    exitSpeed: speeds.exitSpeed,
    arrivalSpeed: speeds.arrivalSpeed,
    tumbleX: tumbleX,
    tumbleY: tumbleY,
    spins: spins,
  };
}

/**
 * Advance one step. `random` returns a number in [0, 1).
 * `rollAngle` is the ground roll for the signed distance the center moves.
 * `spins` is the same roll plus the tumble picked up at the last bounce.
 */
function stepSphere(state, dt, random) {
  var x = state.x;
  var velocity = state.velocityX;
  var limit = state.limit;
  var speeds = readSpeeds(state, velocity);
  var tumbleX = state.tumbleX || 0;
  var tumbleY = state.tumbleY || 0;
  if (!(dt > 0) || !(limit > 0.02)) {
    return finishedStep(x, velocity, 0, false, speeds, tumbleX, tumbleY, []);
  }
  if (x > limit) {
    x = limit;
  } else if (x < -limit) {
    x = -limit;
  }

  var remaining = dt;
  var traveled = 0;
  var bounced = false;
  var spins = [];
  var passes = 0;
  while (remaining > 0.0000001 && passes < 8) {
    passes += 1;
    var direction = velocity < 0 ? -1 : 1;
    var span = limit * 2;
    var origin = -direction * limit;
    var covered = (x - origin) * direction;
    if (covered < 0) {
      covered = 0;
    } else if (covered > span) {
      covered = span;
    }
    var slope = (speeds.arrivalSpeed - speeds.exitSpeed) / span;
    var speed = speeds.exitSpeed + slope * covered;
    if (!(speed > 0)) {
      speed = MIN_SPEED;
      slope = 0;
    }

    var distanceToWall = span - covered;
    if (distanceToWall <= 0.0000001) {
      speeds = pickBounceSpeeds(speed, random);
      var tumble = pickTumble(random);
      tumbleX = tumble.x;
      tumbleY = tumble.y;
      velocity = -direction * speeds.exitSpeed;
      x = direction * limit;
      bounced = true;
      continue;
    }

    var timeToWall = timeForDistance(speed, slope, distanceToWall);
    if (!(timeToWall > 0) || timeToWall > remaining) {
      var moved = distanceForTime(speed, slope, remaining);
      if (moved > distanceToWall) {
        moved = distanceToWall;
      }
      var signed = direction * moved;
      x += signed;
      traveled += signed;
      pushSpin(spins, signed, tumbleX, tumbleY);
      var nextSpeed = speed + slope * moved;
      if (!(nextSpeed > 0)) {
        nextSpeed = MIN_SPEED;
      }
      velocity = direction * nextSpeed;
      remaining = 0;
      break;
    }

    var signedToWall = direction * distanceToWall;
    x = direction * limit;
    traveled += signedToWall;
    pushSpin(spins, signedToWall, tumbleX, tumbleY);
    remaining -= timeToWall;
    speeds = pickBounceSpeeds(speeds.arrivalSpeed, random);
    var nextTumble = pickTumble(random);
    tumbleX = nextTumble.x;
    tumbleY = nextTumble.y;
    velocity = -direction * speeds.exitSpeed;
    bounced = true;
  }

  return finishedStep(x, velocity, traveled, bounced, speeds, tumbleX, tumbleY, spins);
}

/**
 * Shadow center and width, in percent of the banner.
 * `halfWidth` is the visible half-width at the sphere, in world units.
 * `impact` is 1 on a bounce and eases back to 0.
 */
function shadowPlacement(x, halfWidth, impact) {
  var safeHalf = halfWidth > 0.001 ? halfWidth : 0.001;
  var strength = impact > 0 ? impact : 0;
  if (strength > 1) {
    strength = 1;
  }
  var width = (100 / safeHalf) * (0.64 + strength * 0.18);
  if (width > 72) {
    width = 72;
  }
  return {
    left: 50 + (x / (2 * safeHalf)) * 100,
    width: width,
  };
}

module.exports = {
  BASE_SPEED: BASE_SPEED,
  MAX_SPEED: PEAK_SPEED,
  MIN_SPEED: MIN_SPEED,
  PEAK_SPEED: PEAK_SPEED,
  RADIUS: RADIUS,
  createRollState: createRollState,
  pickBounceSpeeds: pickBounceSpeeds,
  pickTumble: pickTumble,
  shadowPlacement: shadowPlacement,
  silhouetteAngle: silhouetteAngle,
  stepSphere: stepSphere,
  travelLimit: travelLimit,
};
