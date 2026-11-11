<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';

type SpiderItem = {
  label?: string;
  code?: string;
  name?: string;
  type?: string;
  actual?: number | string | null;
  expected?: number | string | null;
};

const props = withDefaults(defineProps<{
  items: SpiderItem[];
  maxLevel?: number;
  /** Small print-friendly chart: no card header, tighter geometry; the viewBox is fitted to the drawn labels. */
  compact?: boolean;
}>(), {
  maxLevel: 5,
  compact: false,
});

const size = 640;
const center = size / 2;
const padX = 50; // extra horizontal room so side labels are not clipped
// Compact charts draw the polygon smaller and the label text larger, so names stay legible when printed small.
const geo = computed(() => props.compact
  ? { radius: 130, labelRadius: 158, lineHeight: 23, maxLine: 13 }
  : { radius: 175, labelRadius: 205, lineHeight: 19, maxLine: 15 });
const svgEl = ref<SVGSVGElement | null>(null);
const fitBox = ref('');
const measure = async () => {
  if (!props.compact) return;
  await nextTick();
  const box = svgEl.value?.getBBox();
  if (box && box.width > 0) fitBox.value = `${box.x - 6} ${box.y - 6} ${box.width + 12} ${box.height + 12}`;
};
onMounted(() => { void measure(); void document.fonts?.ready.then(measure); });
watch(() => props.items, measure, { deep: true });
const chartItems = computed(() => props.items.map((item, index) => {
  const angle = (-Math.PI / 2) + ((Math.PI * 2 * index) / props.items.length);
  return { ...item, angle };
}));
const point = (angle: number, value: number, targetRadius = geo.value.radius) => {
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
const graphemes = (text: string) => [...new Intl.Segmenter('th', { granularity: 'grapheme' }).segment(text)].map((part) => part.segment);
// Wrap on Thai word boundaries; a trailing "(…)" qualifier gets its own line.
const wrapName = (name: string) => {
  const match = name.match(/^(.*?)\s*(\([^)]*\))$/);
  const parts = match ? [match[1], match[2]] : [name];
  const lines: string[] = [];
  parts.forEach((part) => {
    let line = '';
    [...new Intl.Segmenter('th', { granularity: 'word' }).segment(part)].forEach(({ segment }) => {
      if (graphemes(line + segment).length > geo.value.maxLine && line.trim()) {
        lines.push(line.trim());
        line = '';
      }
      if (graphemes(segment).length > geo.value.maxLine) {
        const chars = graphemes(segment);
        for (let index = 0; index < chars.length; index += geo.value.maxLine) {
          const chunk = chars.slice(index, index + geo.value.maxLine).join('');
          if (index + geo.value.maxLine < chars.length) lines.push(chunk); else line = chunk;
        }
        return;
      }
      line += segment;
    });
    if (line.trim()) lines.push(line.trim());
  });
  return lines.length ? lines : ['-'];
};
const typeClass = (type?: string) => {
  const value = String(type || '').toUpperCase();
  if (value.startsWith('FC')) return 'type-fc';
  if (value === 'MC') return 'type-mc';
  return 'type-cc';
};
const labelFor = (item: SpiderItem & { angle: number }) => {
  const cos = Math.cos(item.angle);
  const sin = Math.sin(item.angle);
  const x = center + cos * geo.value.labelRadius;
  const y = center + sin * geo.value.labelRadius;
  const LINE_HEIGHT = geo.value.lineHeight;
  const anchor = cos > 0.25 ? 'start' : (cos < -0.25 ? 'end' : 'middle');
  // The compact chart keeps the type only; the code is in the table next to it.
  const heading = (props.compact ? [item.type] : [item.type, item.code]).filter(Boolean).join(' · ');
  const nameLines = wrapName(item.name || item.code || '-');
  const total = nameLines.length + (heading ? 1 : 0);
  // Above the chart the block grows upward, below it grows downward, at the sides it is centred.
  // Only labels straight above/below grow up/down; side labels centre on the axis end so neighbours never collide.
  const isSide = anchor !== 'middle';
  const firstBaseline = !isSide && sin < 0
    ? y - (total - 1) * LINE_HEIGHT - 10
    : (!isSide ? y + 10 : y - ((total - 1) * LINE_HEIGHT) / 2 + 4);
  return { x, anchor, heading, nameLines, firstBaseline };
};
</script>

<template>
  <section class="spider-card" :class="{ 'is-compact': compact }" aria-labelledby="competency-spider-title">
    <header v-if="!compact">
      <div>
        <h2 id="competency-spider-title">ภาพรวมสมรรถนะ</h2>
      </div>
      <div class="legend" aria-label="คำอธิบายสีกราฟ">
        <span><i class="actual"></i>คะแนนจริง</span>
        <span><i class="expected"></i>ระดับคาดหวัง</span>
      </div>
    </header>

    <div class="spider-content">
    <div v-if="chartItems.length >= 3" class="chart-wrap">
      <svg ref="svgEl" :viewBox="compact && fitBox ? fitBox : `${-padX} 0 ${size + padX * 2} ${size}`" role="img" aria-label="กราฟใยแมงมุมเปรียบเทียบคะแนนจริงและระดับคาดหวัง">
        <g class="grid">
          <polygon v-for="level in maxLevel" :key="level" :points="ringPoints(level)" />
          <line
            v-for="item in chartItems"
            :key="`axis-${item.code}-${item.angle}`"
            :x1="center"
            :y1="center"
            :x2="center + Math.cos(item.angle) * geo.radius"
            :y2="center + Math.sin(item.angle) * geo.radius"
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
            :x="labelFor(item).x"
            :y="labelFor(item).firstBaseline"
            :text-anchor="labelFor(item).anchor"
          >
            <title>{{ [item.type, item.name || item.code].filter(Boolean).join(' · ') }}</title>
            <tspan v-if="labelFor(item).heading" class="axis-type" :class="typeClass(item.type)" :x="labelFor(item).x">{{ labelFor(item).heading }}</tspan>
            <tspan
              v-for="(line, lineIndex) in labelFor(item).nameLines"
              :key="lineIndex"
              class="axis-name"
              :x="labelFor(item).x"
              :dy="lineIndex === 0 && !labelFor(item).heading ? 0 : geo.lineHeight"
            >{{ line }}</tspan>
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
.chart-wrap { min-width: 0; width: 100%; box-sizing: border-box; margin: 0; padding: 10px 8px 12px; overflow-x: auto; }
svg { display: block; width: 100%; max-width: 520px; max-height: 62vh; min-width: 420px; height: auto; margin: 0 auto; overflow: visible; }
.grid polygon, .grid line { fill: none; stroke: #d9e2e8; stroke-width: 1; }
.expected-shape { fill: rgba(217, 119, 6, .08); stroke: #d97706; stroke-dasharray: 6 5; stroke-width: 2; }
.actual-shape { fill: rgba(15, 118, 110, .18); stroke: #0f766e; stroke-width: 3; }
.actual-points circle { fill: #fff; stroke: #0f766e; stroke-width: 3; }
.labels text { fill: var(--text3); font-size: 10px; font-weight: 700; }
.labels .axis-type { font-size: 15px; font-weight: 900; letter-spacing: .02em; }
.labels .type-cc { fill: #0f766e; }
.labels .type-fc { fill: #c2410c; }
.labels .type-mc { fill: #6d28d9; }
.labels .axis-name { fill: var(--text); font-size: 17px; font-weight: 600; }
.chart-empty { padding: 34px 18px; color: var(--text3); font-size: 12px; font-weight: 700; text-align: center; }
@media (max-width: 900px) {
  .spider-card > header { display: grid; }
  .chart-wrap { overflow-x: auto; padding-inline: 8px; }
}
.spider-card.is-compact { border: 0; border-radius: 0; box-shadow: none; background: transparent; }
.spider-card.is-compact .chart-wrap { padding: 0; overflow: visible; }
.spider-card.is-compact svg { min-width: 0; width: 100%; }
.spider-card.is-compact .labels .axis-name { font-size: 21px; }
.spider-card.is-compact .labels .axis-type { font-size: 18px; }
.spider-card.is-compact .actual-points circle { r: 5; }
</style>
