<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{ user: any; setUsers: any }>();

const assessDraftKey = `cidp-employee-assess:${props.user?.sso || 'default'}`;

const readStorage = (key: string, fallback: any) => {
  try { const raw = localStorage.getItem(key); return raw ? JSON.parse(raw) : fallback; } catch { return fallback; }
};
const writeStorage = (key: string, value: any) => {
  try { localStorage.setItem(key, JSON.stringify(value)); } catch {}
};

const expanded = ref<number | null>(null);
const scores = ref<any>(readStorage(assessDraftKey, { 0: 3, 1: 4, 2: 2, 3: 3, 4: 2 }));

watch(scores, (val) => writeStorage(assessDraftKey, val), { deep: true });

const submitEval = () => {
  props.setUsers((prev: any[]) =>
    prev.map((u: any) => u.sso === props.user.sso ? { ...u, evalStatus: 'self_submitted' } : u)
  );
  alert('ส่งแบบประเมินให้หัวหน้าสำเร็จ!\nสถานะเปลี่ยนเป็น self_submitted');
};

const saveAssessDraft = () => {
  writeStorage(assessDraftKey, scores.value);
  alert('บันทึกร่างเรียบร้อย');
};

const sections: any = {
  CC: [
    { cd: 'CC-001', n: 'การบริการที่ดี', levels: { 1: ['รับเรื่องและตอบคำถามพื้นฐานตามแนวทางที่กำหนด', 'แสดงมารยาทที่เหมาะสมต่อผู้รับบริการ', 'ส่งต่อเรื่องที่เกินขอบเขตให้ผู้เกี่ยวข้อง'], 2: ['ตอบสนองความต้องการผู้รับบริการได้ทันท่วงที', 'ให้ข้อมูลที่ถูกต้องและครบถ้วนแก่ผู้รับบริการ', 'แสดงความเป็นมิตร ยิ้มแย้ม ให้บริการด้วยใจ'], 3: ['ติดตามผลจนผู้รับบริการได้รับคำตอบ', 'ปรับวิธีสื่อสารให้เหมาะกับสถานการณ์', 'ประสานงานเพื่อแก้ปัญหาบริการที่ซับซ้อนขึ้น'], 4: ['คาดการณ์ปัญหาบริการและป้องกันล่วงหน้า', 'ปรับปรุงขั้นตอนบริการให้ลดความผิดพลาดซ้ำ', 'เป็นที่ปรึกษาให้เพื่อนร่วมงานด้านการบริการ'], 5: ['กำหนดมาตรฐานบริการที่ยกระดับประสบการณ์ผู้รับบริการ', 'ใช้ข้อเสนอแนะเพื่อพัฒนาระบบบริการทั้งหน่วยงาน', 'สร้างวัฒนธรรมบริการที่ดีอย่างต่อเนื่อง'] } },
    { cd: 'CC-003', n: 'การทำงานเป็นทีม', levels: { 1: ['รับผิดชอบงานของตนในทีมตามที่ได้รับมอบหมาย', 'รับฟังข้อมูลพื้นฐานจากสมาชิกทีม', 'แจ้งปัญหาที่กระทบงานทีมได้'], 2: ['แบ่งปันข้อมูลและทรัพยากรกับทีมอย่างเต็มที่', 'รับฟังความคิดเห็นผู้อื่นด้วยใจเปิดกว้าง', 'ช่วยเหลือเพื่อนร่วมงานเมื่อมีปัญหา'], 3: ['ประสานงานให้สมาชิกทำงานร่วมกันได้ต่อเนื่อง', 'จัดการความเห็นต่างด้วยเหตุผล', 'สนับสนุนให้ทีมส่งมอบงานตามเป้าหมาย'], 4: ['เชื่อมทีมข้ามหน่วยเพื่อแก้ปัญหาร่วมกัน', 'สร้างบรรยากาศที่สมาชิกกล้าแลกเปลี่ยน', 'โค้ชสมาชิกให้ทำงานร่วมกันได้มีประสิทธิภาพ'], 5: ['ออกแบบรูปแบบความร่วมมือที่ทีมอื่นนำไปใช้ได้', 'ขับเคลื่อนทีมผ่านสถานการณ์ซับซ้อน', 'สร้างเครือข่ายความร่วมมือระยะยาว'] } },
    { cd: 'CC-004', n: 'จริยธรรมและความซื่อสัตย์', levels: { 1: ['ปฏิบัติตามกฎ ระเบียบ และคำแนะนำที่เกี่ยวข้อง', 'หลีกเลี่ยงการใช้ข้อมูลโดยไม่เหมาะสม', 'รายงานข้อผิดพลาดของตนตามจริง'], 2: ['ปฏิบัติงานด้วยความซื่อสัตย์สุจริต', 'รักษาความลับขององค์กรได้เป็นอย่างดี', 'ยึดมั่นในหลักจริยธรรมวิชาชีพ'], 3: ['ตัดสินใจโดยคำนึงถึงผลกระทบทางจริยธรรม', 'ชี้แจงข้อมูลอย่างโปร่งใสตรวจสอบได้', 'เตือนหรือแนะนำเพื่อนร่วมงานเมื่อพบความเสี่ยง'], 4: ['จัดการประเด็นจริยธรรมที่ซับซ้อนด้วยความรอบคอบ', 'ส่งเสริมระบบงานที่โปร่งใสและลดผลประโยชน์ทับซ้อน', 'เป็นแบบอย่างด้านความรับผิดชอบ'], 5: ['วางแนวปฏิบัติด้านจริยธรรมให้หน่วยงาน', 'สร้างความเชื่อมั่นต่อผู้มีส่วนได้ส่วนเสีย', 'ขับเคลื่อนวัฒนธรรมความซื่อสัตย์อย่างยั่งยืน'] } },
  ],
  MC: [],
  FC: [
    { cd: 'FC2-061', n: 'การใช้เทคโนโลยีดิจิทัล', levels: { 1: ['ใช้เครื่องมือดิจิทัลพื้นฐานตามขั้นตอน', 'จัดเก็บไฟล์งานให้ค้นหาได้', 'ขอความช่วยเหลือเมื่อพบปัญหาการใช้งาน'], 2: ['ใช้โปรแกรมสำนักงานได้คล่องแคล่ว', 'เลือกเครื่องมือดิจิทัลที่เหมาะกับงานประจำ', 'รักษาความปลอดภัยข้อมูลพื้นฐานได้'], 3: ['ใช้เครื่องมือดิจิทัลช่วยวิเคราะห์และติดตามงาน', 'ประยุกต์ใช้ระบบร่วมงานออนไลน์ได้', 'ใช้ AI เบื้องต้นเพื่อเพิ่มประสิทธิภาพงานอย่างเหมาะสม'], 4: ['ปรับปรุงกระบวนการงานด้วยเทคโนโลยี', 'แนะนำเครื่องมือดิจิทัลให้ทีมใช้งานร่วมกัน', 'ประเมินความเสี่ยงข้อมูลจากการใช้เครื่องมือใหม่'], 5: ['ออกแบบแนวทางดิจิทัลที่สร้างผลลัพธ์ระดับหน่วยงาน', 'ผลักดันการใช้เทคโนโลยีอย่างมีธรรมาภิบาล', 'ติดตามแนวโน้มเทคโนโลยีเพื่อยกระดับงาน'] } },
    { cd: 'FC2-062', n: 'การวิเคราะห์ข้อมูล', levels: { 1: ['รวบรวมข้อมูลจากแหล่งที่กำหนดได้', 'ตรวจสอบข้อมูลเบื้องต้นตามแบบฟอร์ม', 'สรุปข้อเท็จจริงง่าย ๆ จากข้อมูลที่มี'], 2: ['จัดหมวดหมู่และตรวจความครบถ้วนของข้อมูล', 'เปรียบเทียบข้อมูลพื้นฐานเพื่อหาความต่าง', 'สร้างตารางหรือกราฟพื้นฐานประกอบรายงาน'], 3: ['วิเคราะห์ข้อมูลอย่างเป็นระบบตามโจทย์งาน', 'นำเสนอข้อมูลในรูปแบบที่เข้าใจง่าย', 'ใช้ข้อมูลสนับสนุนการตัดสินใจและแก้ปัญหา'], 4: ['วิเคราะห์ข้อมูลหลายมิติและตรวจความน่าเชื่อถือ', 'อธิบายแนวโน้มและปัจจัยที่เกี่ยวข้อง', 'เสนอทางเลือกจากผลวิเคราะห์ให้ผู้เกี่ยวข้อง'], 5: ['ออกแบบกรอบวิเคราะห์ข้อมูลให้ทีมใช้ร่วมกัน', 'คาดการณ์ผลกระทบจากข้อมูลเชิงลึก', 'พัฒนาการใช้ข้อมูลเพื่อยกระดับการตัดสินใจ'] } },
  ],
};

const typeConfig: any = {
  CC: { label: 'CC — Core Competency', tag: 'tag-cc', tagLabel: 'CC', color: '#1E40AF' },
  MC: { label: 'MC — Managerial Competency', tag: 'tag-mc', tagLabel: 'MC', color: '#6D28D9' },
  FC: { label: 'FC — Functional Competency', tag: 'tag-fc', tagLabel: 'FC', color: '#065f46' },
};

const scoreLabels = ['ต่ำมาก', 'ต่ำ', 'พอใช้', 'ดี', 'ดีมาก'];

const getBehaviors = (item: any, score?: number) => {
  if (!score) return [];
  return item.levels[score] || [];
};

// flatten all items with global index
const allItems: { type: string; item: any; globalIdx: number }[] = [];
Object.entries(sections).forEach(([type, items]: any) => {
  items.forEach((item: any) => {
    allItems.push({ type, item, globalIdx: allItems.length });
  });
});
</script>

<template>
  <div>
    <div class="flex ic jb mb20">
      <div>
        <div class="sec-t">ประเมินตนเอง </div>
        <div class="sec-s">รอบปี 2568 · กรอกให้ครบทุกสมรรถนะแล้วกด "ส่งให้หัวหน้า"</div>
      </div>
      <span class="b by">draft</span>
    </div>

    <div style="background: var(--yellow-bg); border: 1px solid #FDE68A; border-radius: var(--r); padding: 10px 14px; margin-bottom: 20px; font-size: 12px; color: var(--yellow);">
       กรุณาเลือกคะแนนที่ตรงกับความสามารถที่คุณทำได้จริง อ้างอิงพฤติกรรมบ่งชี้ด้านล่าง
    </div>

    <template v-for="[type, items] in Object.entries(sections)" :key="type">
      <div v-if="items && items.length > 0" style="margin-bottom: 24px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 2px solid var(--border);">
          <span :style="{ background: typeConfig[type].color, color: '#fff', padding: '3px 10px', borderRadius: '5px', fontSize: '11px', fontWeight: 800 }">
            {{ typeConfig[type].tagLabel }}
          </span>
          <span style="font-size: 14px; font-weight: 700; color: var(--text);">{{ typeConfig[type].label }}</span>
        </div>

        <template v-for="item in items" :key="item.cd">
          <div
            v-if="allItems.find(i => i.item.cd === item.cd)"
            class="ac"
            style="margin-bottom: 8px;"
          >
            <div
              class="ah"
              style="background: #fff; cursor: pointer;"
              @click="expanded = expanded === allItems.find(i => i.item.cd === item.cd)!.globalIdx ? null : allItems.find(i => i.item.cd === item.cd)!.globalIdx"
            >
              <span :class="typeConfig[type].tag" style="flex-shrink: 0;">{{ typeConfig[type].tagLabel }}</span>
              <span class="fw7 fs13" style="flex: 1; margin-left: 2px;">{{ item.n }}</span>
              <span :style="{ fontSize: '12px', fontWeight: 700, color: scores[allItems.find(i => i.item.cd === item.cd)!.globalIdx] ? 'var(--teal)' : 'var(--red)' }">
                {{ scores[allItems.find(i => i.item.cd === item.cd)!.globalIdx] ? scores[allItems.find(i => i.item.cd === item.cd)!.globalIdx] + ' / 5 ✓' : 'ยังไม่กรอก' }}
              </span>
              <span style="margin-left: 10px; color: var(--text3); font-size: 12px;">
                {{ expanded === allItems.find(i => i.item.cd === item.cd)!.globalIdx ? '▴' : '▾' }}
              </span>
            </div>

            <div :class="['ab', expanded === allItems.find(i => i.item.cd === item.cd)!.globalIdx ? 'open' : '']">
              <div style="margin-bottom: 14px;">
                <div class="lbl mb6" style="font-size: 11px;">พฤติกรรมบ่งชี้ (ใช้ประกอบการตัดสิน)</div>
                <template v-if="scores[allItems.find(i => i.item.cd === item.cd)!.globalIdx]">
                  <div class="fs11" style="padding: 8px 10px; border-radius: 8px; background: var(--blue-lt); color: var(--blue); margin-bottom: 8px;">
                    ระดับ {{ scores[allItems.find(i => i.item.cd === item.cd)!.globalIdx] }}: {{ scoreLabels[scores[allItems.find(i => i.item.cd === item.cd)!.globalIdx] - 1] }}
                  </div>
                  <ul class="blist">
                    <li v-for="(b, i) in getBehaviors(item, scores[allItems.find(ai => ai.item.cd === item.cd)!.globalIdx])" :key="i">{{ b }}</li>
                  </ul>
                </template>
                <div v-else class="muted fs12">เลือกคะแนนความสามารถเพื่อดูพฤติกรรมบ่งชี้ของระดับนั้น</div>
              </div>

              <div class="lbl mb8">คะแนนความสามารถของคุณ <span style="color: var(--red);">*</span></div>
              <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-bottom: 14px;">
                <div
                  v-for="k in [1,2,3,4,5]"
                  :key="k"
                  :style="{
                    border: `2px solid ${scores[allItems.find(i => i.item.cd === item.cd)!.globalIdx] === k ? 'var(--teal)' : 'var(--border)'}`,
                    borderRadius: '10px', padding: '14px 8px', cursor: 'pointer',
                    background: scores[allItems.find(i => i.item.cd === item.cd)!.globalIdx] === k ? 'var(--teal-lt)' : '#fff',
                    transition: '.15s', textAlign: 'center'
                  }"
                  @click="scores[allItems.find(i => i.item.cd === item.cd)!.globalIdx] = k"
                >
                  <div :style="{ fontSize: '26px', fontWeight: 800, color: scores[allItems.find(i => i.item.cd === item.cd)!.globalIdx] === k ? 'var(--teal)' : 'var(--navy)', lineHeight: 1 }">{{ k }}</div>
                  <div style="font-size: 10px; color: var(--text3); margin-top: 4px;">{{ scoreLabels[k - 1] }}</div>
                </div>
              </div>

              <div class="divider" />
              <div class="lbl mb8" style="font-size: 11px;">แนบหลักฐานประกอบ <span class="lbl-opt">(ถ้ามี)</span></div>
              <div class="g2">
                <div class="upload-area" style="padding: 12px;">
                  <div style="font-size: 18px; margin-bottom: 4px;"></div>
                  <div class="fw6 fs12">อัปโหลดไฟล์</div>
                  <div class="muted fs11">PDF, Word, Excel, รูปภาพ</div>
                </div>
                <div>
                  <div class="fg">
                    <label class="lbl" style="font-weight: 500; font-size: 11px;">URL หลักฐาน</label>
                    <input class="inp" style="font-size: 12px;" placeholder="https://..." />
                  </div>
                  <div class="fg mb0">
                    <label class="lbl" style="font-weight: 500; font-size: 11px;">คำอธิบาย</label>
                    <textarea class="ta" style="min-height: 48px; font-size: 12px;" placeholder="อธิบายสั้นๆ..." />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </template>

    <div class="flex g8 mt4" style="padding-top: 4px;">
      <button class="btn btn-t" @click="submitEval"> ส่งให้หัวหน้า</button>
      <button class="btn btn-s" @click="saveAssessDraft">บันทึกร่าง</button>
    </div>
  </div>
</template>
