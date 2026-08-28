export const particleVertex = /* glsl */ `
  attribute float aSeed;
  attribute float aSize;
  uniform float uTime;
  uniform float uPixelRatio;
  varying float vAlpha;
  varying float vSeed;

  void main() {
    vSeed = aSeed;
    vec3 p = position;
    float t = uTime * 0.22 + aSeed * 6.283185;
    p.x += sin(t) * 0.18;
    p.y += cos(t * 0.85 + aSeed) * 0.14;
    p.z += sin(t * 0.6) * 0.12;
    vec4 mv = modelViewMatrix * vec4(p, 1.0);
    gl_Position = projectionMatrix * mv;
    gl_PointSize = aSize * uPixelRatio * (140.0 / max(1.2, -mv.z));
    vAlpha = smoothstep(36.0, 7.0, -mv.z);
  }
`;

export const particleFragment = /* glsl */ `
  uniform float uTime;
  uniform vec3 uC1;
  uniform vec3 uC2;
  varying float vAlpha;
  varying float vSeed;

  void main() {
    vec2 uv = gl_PointCoord - 0.5;
    float d = length(uv);
    if (d > 0.5) discard;
    float twinkle = 0.5 + 0.5 * sin(uTime * 2.4 + vSeed * 40.0);
    float glow = pow(smoothstep(0.5, 0.0, d), 1.4);
    vec3 col = mix(uC1, uC2, fract(vSeed * 3.71));
    gl_FragColor = vec4(col, glow * vAlpha * twinkle);
  }
`;

export const ribbonVertex = /* glsl */ `
  varying vec2 vUv;
  void main() {
    vUv = uv;
    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
  }
`;

export const ribbonFragment = /* glsl */ `
  uniform float uTime;
  uniform vec3 uC1;
  uniform vec3 uC2;
  uniform vec3 uC3;
  uniform vec3 uC4;
  varying vec2 vUv;

  void main() {
    float t = fract(vUv.x * 0.55 + uTime * 0.07);
    vec3 col = mix(uC1, uC2, smoothstep(0.0, 0.33, t));
    col = mix(col, uC3, smoothstep(0.33, 0.66, t));
    col = mix(col, uC4, smoothstep(0.66, 1.0, t));
    float edge = smoothstep(0.0, 0.18, vUv.y) * smoothstep(1.0, 0.82, vUv.y);
    float pulse = 0.72 + 0.28 * sin(uTime * 1.4 + vUv.x * 12.0);
    gl_FragColor = vec4(col, edge * pulse * 0.82);
  }
`;
