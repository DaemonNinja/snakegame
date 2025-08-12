<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Slither-Like Snake (v8)</title>
<style>
  :root{
    --bg:#0f0f10;
    --panel:#171717;
    --accent:#2a2a2a;
    --muted:#999;
  }
  html,body{
    height:100%;
    margin:0;
    background:var(--bg);
    color:#eee;
    font-family:Inter,Arial,Helvetica,sans-serif;
    -webkit-font-smoothing:antialiased;
    -moz-osx-font-smoothing:grayscale;
  }

  /* page layout */
  .page {
    height:100%;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:22px;
    box-sizing:border-box;
  }

  #wrap {
    display:flex;
    gap:26px;
    align-items:flex-start;
    justify-content:center;
  }

  /* game canvas */
  #game-wrap{
    position:relative;
    display:block;
    width:840px;
    height:620px;
  }
  canvas {
    display:block;
    background-color:#1b1b1b;
    border-radius:8px;
    border:3px solid #333;
    box-shadow: 0 6px 24px rgba(0,0,0,0.6);
    width:840px;
    height:620px;
  }

  /* right column */
  #rightcol{
    width:260px;
    display:flex;
    flex-direction:column;
    gap:12px;
  }
  .panel {
    background:var(--panel);
    border-radius:10px;
    border:2px solid #2b2b2b;
    padding:12px;
    box-sizing:border-box;
  }

  /* leaderboard */
  #leaderboard h2{
    margin:0 0 8px 0;
    font-size:18px;
    text-align:center;
  }
  .leader-item{
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:8px;
    border-radius:6px;
    margin-bottom:8px;
    background:linear-gradient(180deg,#181818, #151515);
    border:1px solid #222;
  }
  .leader-left{
    display:flex;
    align-items:center;
    gap:10px;
  }
  .rank{
    font-weight:700;
    color:#f6c84c;
    min-width:36px;
    text-align:center;
  }
  .player-name{
    font-weight:600;
    color:#fff;
  }
  .player-score{
    font-weight:700;
    color:#7eff7e;
  }
  .crown{
    margin-left:8px;
    color:gold;
    font-size:16px;
  }

  /* settings under leaderboard */
  #settings label{
    display:block;
    font-size:13px;
    color:#cfcfcf;
    margin-bottom:6px;
  }
  #settings input[type="text"], #settings select {
    width:100%;
    padding:7px 8px;
    border-radius:6px;
    border:1px solid #333;
    background:#0f0f0f;
    color:#eee;
    box-sizing:border-box;
    margin-bottom:8px;
  }
  #settings .row{
    display:flex;
    gap:8px;
    align-items:center;
  }
  #resetBtn{
    width:100%;
    background:#d64545;
    color:#fff;
    border:none;
    padding:8px 10px;
    border-radius:6px;
    cursor:pointer;
    margin-top:6px;
  }

  /* victory overlay */
  #victory {
    position:absolute;
    inset:0;
    display:flex;
    align-items:center;
    justify-content:center;
    pointer-events:none;
    font-size:48px;
    color:gold;
    text-shadow:0 4px 16px rgba(0,0,0,0.8);
    display:none;
  }

  /* small helper text */
  .muted{
    color:var(--muted);
    font-size:12px;
  }
</style>
</head>
<body>
  <div class="page">
    <div id="wrap">
      <div id="game-wrap" class="panel">
        <canvas id="game" width="840" height="620"></canvas>
        <div id="victory">Victory! 👑</div>
      </div>

      <div id="rightcol">
        <div class="panel" id="leaderboard">
          <h2>Leaderboard</h2>
          <div id="leader-list">
            <!-- rank items injected here -->
          </div>
          <div class="muted" style="text-align:center;font-size:12px;margin-top:6px;">
            First to <strong>300</strong> mass wins
          </div>
        </div>

        <div class="panel" id="settings">
          <label>Player name
            <input id="playerName" type="text" value="Player 1" />
          </label>

          <label>Snake skin
            <select id="skinSelect">
              <option value="lime">Green</option>
              <option value="blue">Blue</option>
              <option value="purple">Purple</option>
              <option value="pink">Pink</option>
            </select>
          </label>

          <label>Control mode
            <select id="controlSelect">
              <option value="mouse">Mouse (smooth)</option>
              <option value="keys">Arrow keys</option>
            </select>
          </label>

          <div style="display:flex;gap:8px;margin-top:6px;">
            <button id="resetBtn">Reset Game</button>
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
/* ===========================
   Game constants & variables
   =========================== */

const canvas = document.getElementById('game');
const ctx = canvas.getContext('2d');

const W = canvas.width, H = canvas.height;

// win condition
const WIN_MASS = 300;

// food types (radius, points, color)
const FOOD_TYPES = [
  { radius: 6, points: 1, color: '#ff5050' },   // small
  { radius: 10, points: 3, color: '#ff8a3d' },  // medium
  { radius: 16, points: 8, color: '#ffd54d' }   // large
];

// settings UI
const playerNameInput = document.getElementById('playerName');
const skinSelect = document.getElementById('skinSelect');
const controlSelect = document.getElementById('controlSelect');
const resetBtn = document.getElementById('resetBtn');
const leaderList = document.getElementById('leader-list');
const victoryDiv = document.getElementById('victory');

// gameplay state
let playerName = playerNameInput.value || 'Player 1';
let skin = skinSelect.value || 'lime';
let controlMode = controlSelect.value || 'mouse';

let mousePos = { x: W/2, y: H/2 };
let keys = { left:false, right:false, up:false, down:false };

// snake physical parameters
let snake = {
  path: [],         // array of positions for smooth body follow
  segments: 24,     // how many "visible" circles initially
  spacing: 6,       // pixel spacing between path points (controls smoothness)
  mass: 0,          // accumulated mass / points
  head: { x: W/2, y: H/2, angle: -Math.PI/2, speed: 2.6 }, // initial modifiers
  radiusBase: 8     // base radius (will scale with mass)
};

// foods
let foods = []; // each: {x,y,typeIndex,spawnTime, pulsePhase}

// timing
let lastTime = performance.now();
const DT = 1000/60; // target ~60fps

/* ===========================
   Helpers
   =========================== */

function randRange(min,max){ return Math.random()*(max-min)+min; }
function clamp(v,a,b){ return Math.max(a, Math.min(b, v)); }
function distance(a,b){ const dx=a.x-b.x, dy=a.y-b.y; return Math.sqrt(dx*dx+dy*dy); }

/* ===========================
   Initialize / spawn foods
   =========================== */

function spawnInitialFoods(count=10){
  foods = [];
  for(let i=0;i<count;i++){
    spawnFood();
  }
}
function spawnFood(){
  const t = FOOD_TYPES[Math.floor(Math.random()*FOOD_TYPES.length)];
  // pick position avoiding center
  let x,y,tries=0;
  do{
    x = Math.floor(randRange(20, W-20));
    y = Math.floor(randRange(20, H-20));
    tries++;
  } while(distance({x,y}, snake.head) < 80 && tries < 40);

  foods.push({
    x, y,
    type: t,
    pulse: Math.random()*Math.PI*2,
    created: performance.now()
  });
}

/* ===========================
   Leaderboard (single player)
   =========================== */

function renderLeaderboard(){
  leaderList.innerHTML = '';
  // we keep a simple single-player leaderboard (can be extended)
  const item = document.createElement('div');
  item.className = 'leader-item';
  const left = document.createElement('div');
  left.className = 'leader-left';
  const rank = document.createElement('div');
  rank.className = 'rank';
  rank.textContent = '#1';
  const name = document.createElement('div');
  name.className = 'player-name';
  name.textContent = playerName + (snake.mass >= WIN_MASS ? ' ' : '');
  left.appendChild(rank);
  left.appendChild(name);

  const right = document.createElement('div');
  right.className = 'player-score';
  right.textContent = snake.mass;

  item.appendChild(left);
  item.appendChild(right);
  if(snake.mass >= WIN_MASS){
    item.classList.add('crowned');
    // add crown icon
    const c = document.createElement('span'); c.className='crown'; c.textContent='👑';
    name.appendChild(c);
    victoryDiv.style.display = 'flex';
  } else {
    victoryDiv.style.display = 'none';
  }
  leaderList.appendChild(item);
}

/* ===========================
   Input handling
   =========================== */

canvas.addEventListener('mousemove', (e)=>{
  const rect = canvas.getBoundingClientRect();
  mousePos.x = (e.clientX - rect.left) * (canvas.width / rect.width);
  mousePos.y = (e.clientY - rect.top) * (canvas.height / rect.height);
});

window.addEventListener('keydown', (e)=>{
  if(controlMode !== 'keys') return;
  if(e.key === 'ArrowLeft') keys.left = true;
  if(e.key === 'ArrowRight') keys.right = true;
  if(e.key === 'ArrowUp') keys.up = true;
  if(e.key === 'ArrowDown') keys.down = true;
});
window.addEventListener('keyup', (e)=>{
  if(controlMode !== 'keys') return;
  if(e.key === 'ArrowLeft') keys.left = false;
  if(e.key === 'ArrowRight') keys.right = false;
  if(e.key === 'ArrowUp') keys.up = false;
  if(e.key === 'ArrowDown') keys.down = false;
});

// UI changes
playerNameInput.addEventListener('input', (e)=>{
  playerName = e.target.value || 'Player';
  renderLeaderboard();
});
skinSelect.addEventListener('change', (e)=>{
  skin = e.target.value;
});
controlSelect.addEventListener('change',(e)=>{
  controlMode = e.target.value;
});
resetBtn.addEventListener('click', resetGame);

/* ===========================
   Reset / init
   =========================== */

function resetGame(){
  snake.head.x = W/2; snake.head.y = H/2;
  snake.head.angle = -Math.PI/2;
  snake.head.speed = 2.6;
  snake.mass = 0;
  snake.segments = 24;
  snake.path = [];
  // initialize path with centered points so body appears
  for(let i=0;i<snake.segments*snake.spacing;i++){
    snake.path.push({ x: snake.head.x, y: snake.head.y });
  }
  foods = [];
  spawnInitialFoods(12);
  victoryDiv.style.display = 'none';
  renderLeaderboard();
}

/* ===========================
   Movement & body smoothing
   =========================== */

/*
 Approach:
 - Head has a continuous position and angle.
 - If controlMode=='mouse', compute angleToTarget and smoothly adjust head.angle toward it with a turning limit.
 - If controlMode=='keys', adjust angle based on key presses (left/right turn) and forward/back adjust speed.
 - Each frame, advance head by speed along angle.
 - Push head position onto snake.path each 'spacing' pixels moved (we store a dense path with fixed spacing).
 - To draw segments, sample positions along snake.path at intervals equal to spacing * i.
 - Body radius = baseRadius + mass * factor (clamped).
*/

function approachAngle(current, target, maxDelta){
  let a = target - current;
  a = (a + Math.PI) % (Math.PI*2) - Math.PI; // normalize -PI..PI
  a = clamp(a, -maxDelta, maxDelta);
  return current + a;
}

function updatePhysics(dt){
  // dt in seconds
  const head = snake.head;

  // target angle (mouse or keys)
  if(controlMode === 'mouse'){
    // angle toward mouse
    const tx = mousePos.x, ty = mousePos.y;
    const targetAngle = Math.atan2(ty - head.y, tx - head.x);
    // turning speed depends on current speed and mass; heavier turns slower
    const baseTurn = 0.12; // radians per frame at base
    const massPenalty = clamp(snake.mass * 0.0009, 0, 0.07);
    const maxTurn = baseTurn - massPenalty;
    head.angle = approachAngle(head.angle, targetAngle, Math.max(0.02, maxTurn));
    // adjust speed: slight easing toward target distance
    const dist = Math.hypot(tx-head.x, ty-head.y);
    // faster when far, slower when close (smooth following)
    const targetSpeed = clamp(1.4 + dist*0.01 - snake.mass*0.002, 1.0, 5.2);
    // lerp speed
    head.speed += (targetSpeed - head.speed) * 0.08;
  } else { // keys control - smooth turning and speed
    if(keys.left) head.angle -= 0.08;
    if(keys.right) head.angle += 0.08;
    const targetSpeed = keys.up ? 4.2 : 2.4;
    head.speed += (targetSpeed - head.speed) * 0.06;
  }

  // move head
  head.x += Math.cos(head.angle) * head.speed;
  head.y += Math.sin(head.angle) * head.speed;

  // wrap-around edges
  if(head.x < 0) head.x += W;
  if(head.x > W) head.x -= W;
  if(head.y < 0) head.y += H;
  if(head.y > H) head.y -= H;

  // add head position to path at spacing intervals: ensure roughly constant spacing in path
  const last = snake.path[snake.path.length-1];
  const dx = head.x - last.x;
  const dy = head.y - last.y;
  const dist = Math.hypot(dx, dy);
  if(dist >= 1){ // push more frequently; spacing used later when sampling
    snake.path.push({ x: head.x, y: head.y });
  }
  // keep path length bounded: segments*spacing*2 to allow growth
  const maxPathLen = Math.max(1200, (snake.segments+60) * snake.spacing);
  if(snake.path.length > maxPathLen) snake.path.splice(0, snake.path.length - maxPathLen);
}

/* ===========================
   Collision & Eating
   =========================== */

function checkFoodCollisions(){
  const head = snake.head;
  // effective head radius (mass-based)
  const headR = getHeadRadius();

  for(let i=foods.length-1;i>=0;i--){
    const f = foods[i];
    const dx = (head.x) - f.x;
    const dy = (head.y) - f.y;
    const d = Math.hypot(dx,dy);
    if(d < headR + f.type.radius*0.9){ // collision
      // gain points/mass
      snake.mass += f.type.points;
      // increase segments proportional to points for a more visible growth
      snake.segments += Math.max(1, Math.floor(f.type.points));
      // respawn that food (replace)
      foods.splice(i,1);
      spawnFood();
      renderLeaderboard();
    }
  }
}

/* ===========================
   Visual radii helpers
   =========================== */

function getHeadRadius(){
  // base + growth by mass
  return clamp(snake.radiusBase + snake.mass * 0.08, 8, 44);
}
function getSegmentRadius(i, totalSegments){
  // progressive taper: head big, tail smaller
  const headR = getHeadRadius();
  const tailR = Math.max(4, headR * 0.38);
  const t = 1 - (i / Math.max(1, totalSegments-1));
  // easing
  const r = tailR + (headR - tailR) * Math.pow(t, 1.15);
  return r;
}

/* ===========================
   Drawing
   =========================== */

function drawBackground(){
  // subtle snake-skin like tiled pattern (using radial-ish dots)
  ctx.save();
  ctx.fillStyle = '#151515';
  ctx.fillRect(0,0,W,H);

  // overlay subtle circular pattern
  ctx.globalAlpha = 0.04;
  ctx.fillStyle = '#9f9f9f';
  const step = 36;
  for(let x=0;x<W;x+=step){
    for(let y=0;y<H;y+=step){
      ctx.beginPath();
      ctx.arc(x+((y/step)&1?18:0), y, 7, 0, Math.PI*2);
      ctx.fill();
    }
  }
  ctx.globalAlpha = 1;
  ctx.restore();
}

function drawFoods(time){
  for(const f of foods){
    // pulsing glow
    f.pulse += 0.03;
    const pulse = (Math.sin(f.pulse)+1)/2 * 0.6 + 0.4; // 0.4 ..1.0
    ctx.save();
    ctx.shadowBlur = 18 * pulse;
    ctx.shadowColor = f.type.color;
    // glowing radial
    const g = ctx.createRadialGradient(f.x, f.y, 0, f.x, f.y, f.type.radius*3);
    g.addColorStop(0, f.type.color);
    g.addColorStop(0.4, f.type.color + '88');
    g.addColorStop(1, 'transparent');
    ctx.fillStyle = g;
    ctx.beginPath();
    ctx.arc(f.x, f.y, f.type.radius*2.2 * pulse, 0, Math.PI*2);
    ctx.fill();
    // center circle
    ctx.shadowBlur = 0;
    ctx.fillStyle = '#fff';
    ctx.beginPath();
    ctx.arc(f.x, f.y, f.type.radius*0.5, 0, Math.PI*2);
    ctx.fill();
    ctx.restore();
  }
}

function drawSnake(){
  // sample positions along path to place each segment.
  // We want 'snake.segments' circles spaced by 'snake.spacing' along the path from head to tail.
  const totalSegments = snake.segments;
  const spacing = snake.spacing;

  // Ensure path has enough points: if not, duplicate head
  while(snake.path.length < totalSegments * spacing + 2){
    snake.path.unshift({ x: snake.head.x, y: snake.head.y });
  }

  // compute sampling indexes
  let points = snake.path;
  // start from head (last element)
  let idx = points.length - 1;
  let built = [];
  let traveled = 0;
  let targetDist = 0;
  for(let s=0; s<totalSegments; s++){
    targetDist = s * spacing;
    // walk backward accumulating distance until reach targetDist
    let accum = 0;
    let i = points.length-1;
    let px = points[points.length-1].x, py = points[points.length-1].y;
    let cur = null;
    let need = targetDist;
    for(i = points.length-1; i>0; i--){
      const nx = points[i-1].x, ny = points[i-1].y;
      const d = Math.hypot(nx-px, ny-py);
      if(accum + d >= need){
        // interpolate between (px,py) and (nx,ny)
        const remain = need - accum;
        const t = (d === 0) ? 0 : (remain / d);
        const ix = px + (nx-px) * t;
        const iy = py + (ny-py) * t;
        cur = { x: ix, y: iy };
        break;
      }
      accum += d;
      px = nx; py = ny;
    }
    if(!cur){
      // fallback to tail
      cur = { x: points[0].x, y: points[0].y };
    }
    built.push(cur);
  }

  // draw from tail to head so overlapping looks nicer
  for(let i = built.length-1; i>=0; i--){
    const pos = built[i];
    const r = getSegmentRadius(i, built.length);
    // gradient/shading
    const g = ctx.createRadialGradient(pos.x, pos.y, r*0.2, pos.x, pos.y, r);
    g.addColorStop(0, '#fff');
    // skin color choices map
    const colorMap = {
      lime: '#6bff6b',
      blue: '#5cc3ff',
      purple: '#a67cff',
      pink: '#ff78d6'
    };
    const base = colorMap[skin] || '#6bff6b';
    g.addColorStop(0.2, base);
    g.addColorStop(1, '#0b0b0b');
    ctx.fillStyle = g;
    ctx.beginPath();
    ctx.arc(pos.x, pos.y, r, 0, Math.PI*2);
    ctx.fill();
  }

  // draw simple eye on head (facing direction) - subtle
  const head = built[0];
  if(head){
    const r = getHeadRadius();
    const eyeOffset = 0.5 * r;
    const eyeX = head.x + Math.cos(snake.head.angle) * eyeOffset;
    const eyeY = head.y + Math.sin(snake.head.angle) * eyeOffset;
    ctx.fillStyle = '#fff';
    ctx.beginPath();
    ctx.arc(eyeX, eyeY, Math.max(1.6, r*0.14), 0, Math.PI*2);
    ctx.fill();
    ctx.fillStyle = '#000';
    ctx.beginPath();
    ctx.arc(eyeX + Math.cos(snake.head.angle)*1.2, eyeY + Math.sin(snake.head.angle)*1.2, Math.max(0.8, r*0.06), 0, Math.PI*2);
    ctx.fill();
  }
}

/* ===========================
   Main loop
   =========================== */

function step(){
  updatePhysics();
  checkFoodCollisions();
  draw();
  requestAnimationFrame(step);
}

function updatePhysics(){
  // update dt & physics multiple small steps for stability
  updatePhysicsSmall();
}

function updatePhysicsSmall(){
  // do a simple fixed-step update; not using dt as movement is frame-based for predictability
  // update head & path
  updatePhysicsCore();
}

function updatePhysicsCore(){
  // move head toward target and update path
  // call with approx stable step
  updatePhysics(1/60);
}

// Slight wrapper to call the correct update with dt arg, but we simplified to above
function updatePhysics(dt=1/60){
  // actual physics update defined earlier
  // we'll call the previously defined function by another name to avoid confusion
}

// We will reimplement updatePhysics logic inline to keep clarity

function updatePhysics(){
  const head = snake.head;
  // compute inputs & adjust turn / speed
  if(controlMode === 'mouse'){
    const tx = mousePos.x, ty = mousePos.y;
    const target = Math.atan2(ty - head.y, tx - head.x);
    // turning limit: heavier snakes turn slower
    const baseTurn = 0.14;
    const slowFactor = clamp(1 - snake.mass * 0.0009, 0.35, 1.0);
    const maxTurn = baseTurn * slowFactor;
    head.angle = approachAngle(head.angle, target, maxTurn);
    // speed determined by distance + mass penalty
    const dist = Math.hypot(tx - head.x, ty - head.y);
    const targetSpeed = clamp(1.6 + dist * 0.007 - snake.mass*0.004, 0.9, 6.2);
    head.speed += (targetSpeed - head.speed) * 0.09;
  } else {
    // keys: left/right influence angle smoothly; up increases speed
    if(keys.left) head.angle -= 0.08;
    if(keys.right) head.angle += 0.08;
    const targetSpeed = keys.up ? 4.5 : 2.1;
    head.speed += (targetSpeed - head.speed) * 0.06;
  }

  // move head
  head.x += Math.cos(head.angle) * head.speed;
  head.y += Math.sin(head.angle) * head.speed;

  // wrap
  if(head.x < 0) head.x += W;
  if(head.x > W) head.x -= W;
  if(head.y < 0) head.y += H;
  if(head.y > H) head.y -= H;

  // add to path: only push when last point is some distance away to keep path density reasonable
  const last = snake.path.length ? snake.path[snake.path.length-1] : null;
  if(!last || Math.hypot(head.x-last.x, head.y-last.y) > 1.2){
    snake.path.push({ x: head.x, y: head.y });
  }
  // trim long path to reasonable size relative to segments & spacing
  const maxPath = Math.max(2000, (snake.segments + 100) * snake.spacing);
  if(snake.path.length > maxPath) snake.path.splice(0, snake.path.length - maxPath);
}

/* ===========================
   Draw frame
   =========================== */

function draw(){
  const now = performance.now();
  // background
  drawBackground();
  // foods
  drawFoods(now);
  // snake drawing (body & head)
  drawSnake();
  // HUD (minimal) and leaderboard render
  renderLeaderboard();
}

/* ===========================
   Start
   =========================== */

resetGame(); // sets up everything initially
requestAnimationFrame(step);

/* ===========================
   Food spawn heartbeat (keeps food interesting)
   =========================== */

setInterval(()=>{
  // occasionally spawn a new food if below threshold
  if(foods.length < 10) spawnFood();
}, 1200);

/* ===========================
   Collision loop: check frequently
   =========================== */

setInterval(()=>{
  checkFoodCollisions();
}, 90);

</script>
</body>
</html>
