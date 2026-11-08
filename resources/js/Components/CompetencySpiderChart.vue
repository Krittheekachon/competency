<script setup lang="ts">
import { computed } from 'vue';

type SpiderItem = {
  code?: string;
  name?: string;
  type?: string;
  actual?: number | string | null;
  expected?: number | string | null;
};

const props = withDefaults(defineProps<{
  items: SpiderItem[];
  maxLevel?: number;
}>(), {
  maxLevel: 5,
});

const size = 640;
const center = size / 2;
const radius = 175;
const labelRadius = 215;
const chartItems = computed(() => props.items.map((item, index) => {
  const angle = (-Math.PI / 2) + ((Math.PI * 2 * index) / props.items.length);
  return { ...item, angle };
}));
const point = (angle: number, value: number, targetRadius = radius) => {
  const safeValue = Math.max(0, Math.min(props.maxLevel, Number(value) || 0));
  const distance = targetRadius * (safeValue / props.maxLevel);
  return `${center + Math.cos(angle) * distance},${center + Math.sin(angle) * distance}`;
};
const ringPoints = (level: number) => chartItems.value
  .map((item) => point(item.angle, level))
  .join(' ');
const valuePoints = (key: 'actual' | 'expected') => chartItems.value
  .map((item) => point(item.angle, Number(item[key])))
  .join(' ');
const labelPosition = (angle: number) => ({
  x: center + Math.cos(angle) * labelRadius,
  y: center + Math.sin(angle) * labelRadius,
  anchor: Math.cos(angle) > 0.25 ? 'start' : (Math.cos(angle) < -0.25 ? 'end' : 'middle'),
});
const nameLines = (item: SpiderItem) => {
  const name = item.name || item.code || '-';
  const segments = [...new Intl.Segmenter('th', { granularity: 'grapheme' }).segment(name)].map((part) => part.segment);
  const lines: string[] = [];
  for (let index = 0; index < segments.length; index += 14) {
    lines.push(segments.slice(index, index + 14).join(''));
  }
  return lines;
};
</script>

<template>
  <section class="spider-card" aria-labelledby="competency-spider-title">
    <header>
      <div>
        <h2 id="competency-spider-title">ภาพรวมสมรรถนะ</h2>
        <p>แสดงผลที่อนุมัติครบทุกประเภท CC, MC และ FC</p>
      </div>
      <div class="legend" aria-label="คำอธิบายสีกราฟ">
        <span><i class="actual"></i>คะแนนจริง</span>
        <span><i class="expected"></i>ระดับคาดหวัง</span>
      </div>
    </header>

    <div class="spider-content">
    <div v-if="chartItems.length >= 3" class="chart-wrap">
      <svg :viewBox="`0 0 ${size} ${size}`" role="img" aria-label="กราฟใยแมงมุมเปรียบเทียบคะแนนจริงและระดับคาดหวัง">
        <g class="grid">
          <polygon v-for="level in maxLevel" :key="level" :points="ringPoints(level)" />
          <line
            v-for="item in chartItems"
            :key="`axis-${item.code}-${item.angle}`"
            :x1="center"
            :y1="center"
            :x2="center + Math.cos(item.angle) * radius"
            :y2="center + Math.sin(item.angle) * radius"
          />
        </g>
        <polygon class="expected-shape" :points="valuePoints('expected')" />
        <polygon class="actual-shape" :points="valuePoints('actual')" />
        <g class="actual-points">
          <circle
            v-for="item in chartItems"
            :key="`point-${item.code}-${item.angle}`"
            :cx="point(item.angle, Number(item.actual)).split(',')[0]"
            :cy="point(item.angle, Number(item.actual)).split(',')[1]"
            r="4"
          />
        </g>
        <g class="labels">
          <text
            v-for="item in chartItems"
            :key="`label-${item.code}-${item.angle}`"
            :x="labelPosition(item.angle).x"
            :y="labelPosition(item.angle).y"
            :text-anchor="labelPosition(item.angle).anchor"
          >
            <title>{{ item.name || item.code || '-' }}</title>
            <tspan v-if="item.code" class="code">{{ item.code }}</tspan>
            <tspan v-for="(line, lineIndex) in nameLines(item)" :key="lineIndex" class="axis-name" :x="labelPosition(item.angle).x" :dy="lineIndex === 0 && !item.code ? 0 : 16" :text-anchor="labelPosition(item.angle).anchor">
              {{ line }}
            </tspan>
          </text>
        </g>
      </svg>
    </div>
    <div v-else class="chart-empty">กราฟจะแสดงเมื่อมีผลอนุมัติอย่างน้อย 3 สมรรถนะ</div>
    </div>
  </section>
</template>

<style scoped>
.spider-card { overflow: hidden; border: 1px solid var(--border); border-radius: 8px; background: #fff; box-shadow: var(--shadow); }
.spider-card > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; border-bottom: 1px solid var(--border); padding: 16px 18px; }
h2 { margin: 0; color: var(--text); font-size: 16px; font-weight: 900; }
p { margin: 4px 0 0; color: var(--text3); font-size: 12px; }
.spider-content { min-width: 0; }
.legend { display: flex; flex-wrap: wrap; gap: 14px; color: var(--color-text-secondary, #555d66); font-size: 13px; font-weight: 600; }
.legend span { display: inline-flex; align-items: center; gap: 6px; }
.legend i { width: 20px; height: 3px; border-radius: 999px; }
.legend .actual { background: #0f766e; }
.legend .expected { border-top: 2px dashed #d97706; }
.chart-wrap { min-width: 0; width: 100%; box-sizing: border-box; margin: 0; padding: 14px 8px 18px; overflow-x: auto; }
svg { display: block; width: 100%; min-width: 560px; height: auto; overflow: visible; }
.grid polygon, .grid line { fill: none; stroke: #d9e2e8; stroke-width: 1; }
.expected-shape { fill: rgba(217, 119, 6, .08); stroke: #d97706; stroke-dasharray: 6 5; stroke-width: 2; }
.actual-shape { fill: rgba(15, 118, 110, .18); stroke: #0f766e; stroke-width: 3; }
.actual-points circle { fill: #fff; stroke: #0f766e; stroke-width: 3; }
.labels text { fill: var(--text3); font-size: 10px; font-weight: 700; }
.labels .code { fill: var(--text); font-size: 11px; font-weight: 900; }
.labels .axis-name { fill: var(--text); font-size: 13px; font-weight: 600; }
.chart-empty { padding: 34px 18px; color: var(--text3); font-size: 12px; font-weight: 700; text-align: center; }
@media (max-width: 900px) {
  .spider-card > header { display: grid; }
  .chart-wrap { overflow-x: auto; padding-inline: 8px; }
}
</style>
