<script lang="tsx">
import { defineComponent, ref, watchEffect, type PropType } from "vue";
const useState = (initial: any) => {
  const state = ref(typeof initial === "function" ? initial() : initial);
  const setState = (next: any) => {
    state.value = typeof next === "function" ? next(state.value) : next;
  };
  return [state, setState] as const;
};

const useEffect = (effect: any) => {
  watchEffect((onCleanup) => {
    const cleanup = effect();
    if (typeof cleanup === "function") onCleanup(cleanup);
  });
};

const React = {
  useState,
  useEffect,
  useRef: ref
};interface AutoSelectProps {label: string;value: string;onChange: (val: string) => void;options: string[];placeholder?: string;required?: boolean;style?: React.CSSProperties;}export const AutoSelect = defineComponent({ name: "AutoSelect", props: Object as PropType<AutoSelectProps>, setup(__props) {const { label, value, onChange, options, placeholder, required, style } = __props as any;
    const [inputValue, setInputValue] = useState(value || "");
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const [upward, setUpward] = useState(false);

    useEffect(() => {
      setInputValue(value || "");
    }, [value]);

    useEffect(() => {
      if (isOpen.value && containerRef.current) {
        const rect = containerRef.current.getBoundingClientRect();
        if (window.innerHeight - rect.bottom < 250) {
          setUpward(true);
        } else {
          setUpward(false);
        }
      }
    }, [isOpen.value]);

    const filtered = options.filter((opt) =>
    opt.toLowerCase().includes(inputValue.value.toLowerCase())
    );return () =>


    <div class="fg" ref={containerRef} style={{ position: "relative", minWidth: "180px", ...style }}>
      <label class="lbl">{label}</label>
      <div style={{ position: "relative" }}>
        <input
          class="inp"
          value={inputValue.value}
          onChange={(e) => {setInputValue(e.target.value);setIsOpen(true);}}
          onFocus={() => setIsOpen(true)}
          placeholder={placeholder}
          required={required}
          autoComplete="off" />
        
        <div style={{
          position: "absolute",
          right: "10px",
          top: "50%",
          transform: `translateY(-50%) ${isOpen.value ? 'rotate(180deg)' : ''}`,
          pointerEvents: "none",
          color: "var(--text3)",
          display: "flex",
          transition: "transform 0.2s"
        }}>
          <svg width="16" height="16" fill="none" viewBox="0 0 20 20">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 8 4 4 4-4" />
          </svg>
        </div>
      </div>

      {isOpen.value &&
      <>
          <div style={{
          position: "absolute",
          bottom: upward.value ? "calc(100% + 4px)" : "auto",
          top: upward.value ? "auto" : "calc(100% + 4px)",
          left: 0,
          right: 0,
          zIndex: 1000,
          background: "#fff",
          border: "1px solid var(--border)",
          borderRadius: "var(--r)",
          boxShadow: "0 4px 12px rgba(0,0,0,0.1)",
          maxHeight: "260px",
          overflowY: "auto",
          animation: upward.value ? "slideUp 0.1s ease-out" : "slideDown 0.1s ease-out"
        }}>
            {filtered.length > 0 ?
          filtered.map((opt) =>
          <div
            key={opt}
            style={{
              padding: "12px 14px",
              cursor: "pointer",
              fontSize: "13px",
              borderBottom: "1px solid #f8fafc",
              background: opt === value ? "var(--blue-lt)" : "transparent",
              color: opt === value ? "var(--blue)" : "var(--text1)",
              fontWeight: opt === value ? 600 : 400,
              transition: "background 0.2s"
            }}
            onMouseDown={(e) => {
              e.preventDefault();
              onChange(opt);
              setInputValue(opt);
              setIsOpen(false);
            }}
            onMouseEnter={(e) => {
              if (opt !== value) e.currentTarget.style.background = "#f1f5f9";
            }}
            onMouseLeave={(e) => {
              if (opt !== value) e.currentTarget.style.background = "transparent";
            }}>
            
                  {opt}
                </div>
          ) :

          <div style={{ padding: "14px", color: "#94a3b8", fontSize: "13px", textAlign: "center" }}>
                ไม่พบข้อมูล
              </div>
          }
          </div>
          <div
          style={{ position: "fixed", inset: 0, zIndex: 900 }}
          onMouseDown={() => {setIsOpen(false);setInputValue(value || "");}} />
        
        </>
      }

      <style>{`
        @keyframes slideDown {
          from { opacity: 0; transform: translateY(-10px); }
          to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideUp {
          from { opacity: 0; transform: translateY(10px); }
          to { opacity: 1; transform: translateY(0); }
        }
      `}</style>
    </div>;} });



export const ExcelImportModal = defineComponent({ name: "ExcelImportModal", props: Object as PropType<{title: string;templateName: string;templateFile: string;onClose: () => void;}>, setup(__props) {const { title, templateName, templateFile, onClose } = __props as any;
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const downloadTemplate = () => {
      const link = document.createElement("a");
      link.href = `/templates/${templateName}`;
      link.download = templateName;
      link.click();
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0];
      if (file) {
        setSelectedFile(file);
      }
    };

    const handleImport = () => {
      if (selectedFile.value) {
        alert(`นำเข้าไฟล์ "${selectedFile.value.name}" เรียบร้อยแล้ว! (Mock Import)`);
        onClose();
      }
    };return () =>


    <div class="mo" style={{ zIndex: 1000 }}>
      <div class="mo-box anim-fade-in" style={{ width: "550px", borderRadius: "24px" }}>
        <div class="mo-h" style={{ padding: "24px 28px", borderBottom: "1px solid #f1f5f9" }}>
          <div>
            <div class="fw8 fs18" style={{ color: "var(--navy)" }}>{title}</div>
            <div class="fs12 muted mt4">อัปโหลดไฟล์ Excel เพื่อนำเข้าข้อมูลเข้าสู่ระบบโดยตรง</div>
          </div>
          <button class="btn-close" onClick={onClose}>✕</button>
        </div>
        <div class="mo-b" style={{ padding: "28px" }}>
          <div class="flex ic jb mb24 p16" style={{ background: "#f8fafc", borderRadius: "var(--r)", border: "1px solid #e2e8f0" }}>
            <div class="flex ic g12">
              <div class="flex ic jc" style={{ width: "40px", height: "40px", background: "#fff", borderRadius: "4px", boxShadow: "0 2px 8px rgba(0,0,0,0.05)", fontSize: "20px" }}>📄</div>
              <div>
                <div class="fw8 fs14" style={{ color: "var(--text2)" }}>ไฟล์แม่แบบ (Template)</div>
                <div class="fs11 muted">ดาวน์โหลดเพื่อเตรียมข้อมูลให้ถูกต้อง</div>
              </div>
            </div>
            <button class="btn btn-s btn-sm flex ic g8" style={{ padding: "8px 16px", borderRadius: "10px", fontWeight: 700, border: "1.5px solid var(--blue)", color: "var(--blue)" }} onClick={downloadTemplate}>
              <span style={{ fontSize: "16px" }}>📥</span> ดาวน์โหลดตาราง
            </button>
          </div>

          <div class="fg">
            <label class="lbl mb12 flex ic jb">
              <span>เลือกไฟล์จากคอมพิวเตอร์ของคุณ</span>
              <span class="fs11 fw4 muted">รองรับไฟล์ .xlsx, .csv</span>
            </label>
            <input
              type="file"
              ref={fileInputRef}
              style={{ display: 'none' }}
              accept=".xlsx, .xls, .csv"
              onChange={handleFileChange} />
            
            <div class="upload-dropzone" onClick={() => fileInputRef.current?.click()}>
              <div class="flex col ic jc" style={{ padding: "60px 40px" }}>
                <div class="upload-icon-pulse mb20">{selectedFile.value ? "✅" : "📊"}</div>
                <div class="fw8 fs16 mb8" style={{ color: "var(--navy)" }}>
                  {selectedFile.value ? selectedFile.value.name : "ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อค้นหา"}
                </div>
                <div class="muted fs12" style={{ maxWidth: "280px", textAlign: "center", lineHeight: "1.6" }}>
                  {selectedFile.value ? `ขนาดไฟล์: ${(selectedFile.value.size / 1024).toFixed(2)} KB` : "ระบบจะตรวจสอบหัวข้อในตารางอัตโนมัติ\nกรุณาตรวจสอบให้แน่ใจว่าไม่มีเซลล์ที่ว่างเปล่าในคอลัมน์ที่จำเป็น"}
                </div>
              </div>
            </div>
          </div>

          <div class="flex g12 mt32" style={{ justifyContent: "flex-end" }}>
            <button class="btn btn-s" onClick={onClose} style={{ minWidth: "100px", borderRadius: "var(--r)" }}>ยกเลิก</button>
            <button
              class="btn btn-p shadow-sm"
              disabled={!selectedFile.value}
              style={{ minWidth: "140px", borderRadius: "var(--r)", opacity: selectedFile.value ? 1 : 0.5, cursor: selectedFile.value ? "pointer" : "not-allowed" }}
              onClick={handleImport}>
              
              เริ่มนำเข้าข้อมูล
            </button>
          </div>
        </div>
      </div>
      <style>{`
        .btn-close { background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; color: var(--text3); cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; font-size: 14px; }
        .btn-close:hover { background: #fee2e2; color: #ef4444; transform: rotate(90deg); }
        .upload-dropzone { border: 2.5px dashed #e2e8f0; border-radius: 20px; transition: 0.3s; cursor: pointer; background: #fafafa; }
        .upload-dropzone:hover { border-color: var(--blue); background: var(--blue-lt); }
        .upload-icon-pulse { font-size: 48px; filter: drop-shadow(0 4px 12px rgba(0,0,0,0.1)); animation: pulse 2s infinite; }
        @keyframes pulse { 0% { transform: scale(1); opacity: 0.8; } 50% { transform: scale(1.1); opacity: 1; } 100% { transform: scale(1); opacity: 0.8; } }
      `}</style>
    </div>;} });

export default defineComponent({ name: "SharedUI" });
</script>
