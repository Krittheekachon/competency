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

interface ProfileProps {user: any;onSave: (nextUser: any) => void;onDirtyChange?: (dirty: boolean) => void;}const splitThaiName = (user: any) => ({ firstName: user?.fn || user?.n?.split(" ")[0] || "", lastName: user?.ln || user?.n?.split(" ").slice(1).join(" ") || "" });const getDisplayLevel = (user: any) => user?.w === "สายงานบริหาร" ? user?.p : user?.l;const Profile = defineComponent({ name: "Profile", props: Object as PropType<ProfileProps>, setup(__props) {const { user, onSave, onDirtyChange } = __props as any;const initialName = splitThaiName(user);const [isEditing, setIsEditing] = useState(false);const [isDirty, setIsDirty] = useState(false);const [photo, setPhoto] = useState(user?.photo || "");const [form, setForm] = useState({
      sso: user?.sso || "",
      title: user?.t || "",
      firstName: initialName.firstName,
      lastName: initialName.lastName,
      firstNameEng: user?.fe || "",
      lastNameEng: user?.le || "",
      gender: user?.g || "",
      workline: user?.w || "",
      group: user?.d || "",
      position: user?.p || "",
      level: getDisplayLevel(user) || "",
      role: user?.r || "employee",
      evaluator1: user?.sup || "",
      evaluator2: user?.evaluator2 || "",
      active: user?.act !== false,
      email: user?.profileSaved ? user?.em || "" : "",
      phone: user?.profileSaved ? user?.ph || "" : "",
      affiliation: user?.profileAffiliation || ""
    });

    useEffect(() => {
      const nextName = splitThaiName(user);
      setIsEditing(false);
      setPhoto(user?.photo || "");
      setForm({
        sso: user?.sso || "",
        title: user?.t || "",
        firstName: nextName.firstName,
        lastName: nextName.lastName,
        firstNameEng: user?.fe || "",
        lastNameEng: user?.le || "",
        gender: user?.g || "",
        workline: user?.w || "",
        group: user?.d || "",
        position: user?.p || "",
        level: getDisplayLevel(user) || "",
        role: user?.r || "employee",
        evaluator1: user?.sup || "",
        evaluator2: user?.evaluator2 || "",
        active: user?.act !== false,
        email: user?.profileSaved ? user?.em || "" : "",
        phone: user?.profileSaved ? user?.ph || "" : "",
        affiliation: user?.profileAffiliation || ""
      });
      setIsDirty(false);
    }, [user]);

    useEffect(() => {
      const hasUnsavedChanges = isEditing.value && isDirty.value;
      onDirtyChange?.(hasUnsavedChanges);

      const warnBeforeUnload = (event: BeforeUnloadEvent) => {
        if (!hasUnsavedChanges) return;
        event.preventDefault();
        event.returnValue = "";
      };

      window.addEventListener("beforeunload", warnBeforeUnload);
      return () => window.removeEventListener("beforeunload", warnBeforeUnload);
    }, [isDirty.value, isEditing.value, onDirtyChange]);

    useEffect(() => () => onDirtyChange?.(false), [onDirtyChange]);

    if (!user) {
      return (
        <div class="card p24">
        <div class="sec-t">โปรไฟล์</div>
        <div class="muted mt8">ไม่พบข้อมูลบุคลากรสำหรับมุมมองนี้</div>
      </div>);

    }

    const setValue = (key: keyof typeof form, value: string) => {
      setForm((current) => ({ ...current, [key]: value }));
      setIsDirty(true);
    };

    const uploadPhoto = (file?: File) => {
      if (!file || !file.type.startsWith("image/")) return;
      const reader = new FileReader();
      reader.onload = () => {
        setPhoto(String(reader.result || ""));
        setIsDirty(true);
      };
      reader.readAsDataURL(file);
    };

    const handleSubmit = (event: Event) => {
      event.preventDefault();
      onSave({
        ...user,
        n: `${form.value.firstName} ${form.value.lastName}`.trim(),
        fn: form.value.firstName,
        ln: form.value.lastName,
        t: form.value.title,
        sso: form.value.sso,
        fe: form.value.firstNameEng,
        le: form.value.lastNameEng,
        g: form.value.gender,
        w: form.value.workline,
        d: form.value.group,
        p: form.value.position,
        l: form.value.workline === "สายงานบริหาร" ? form.value.position : form.value.level,
        r: form.value.role,
        sup: form.value.role === "dean" ? "" : form.value.evaluator1,
        evaluator2: ["employee", "hr", "admin", "supervisor"].includes(form.value.role) ? form.value.evaluator2 : "",
        act: form.value.active,
        em: form.value.email,
        ph: form.value.phone,
        profileAffiliation: form.value.affiliation,
        photo: photo.value,
        profileSaved: true
      });
      setIsDirty(false);
      setIsEditing(false);
      alert("บันทึกโปรไฟล์เรียบร้อยแล้ว");
    };

    const profileName = `${form.value.title}${form.value.firstName} ${form.value.lastName}`.trim();
    const detail = (label: string, value?: string) =>
    <div class="profile-detail">
      <div class="lbl">{label}</div>
      <div class="profile-detail-value">{value || "—"}</div>
    </div>;return () =>



    <form class="profile-page" onSubmit={handleSubmit}>
      <section class="profile-banner">
        <div class="profile-identity">
          <div class="profile-photo-slot">
            <div class="profile-avatar">
              {photo.value ? <img src={photo.value} alt={form.value.firstName} /> : <span>{form.value.firstName[0] || "?"}</span>}
            </div>
            {isEditing.value &&
            <div class="profile-photo-actions">
                <label class="btn btn-s btn-sm profile-upload">
                  เพิ่มรูป
                  <input type="file" accept="image/*" onChange={(e) => uploadPhoto(e.target.files?.[0])} />
                </label>
                {photo.value && <button class="btn btn-s btn-sm" type="button" onClick={() => {setPhoto("");setIsDirty(true);}}>ลบ</button>}
              </div>
            }
          </div>
          <div class="profile-title">
            <div class="sec-t">โปรไฟล์บุคลากร</div>
            <div class="profile-name">{profileName}</div>
            <div class="profile-roleline">
              {form.value.position || "ยังไม่ได้ระบุตำแหน่ง"} · {form.value.level || "ยังไม่ได้ระบุระดับตำแหน่ง"} · {form.value.group || "ยังไม่ได้ระบุกลุ่มงาน"}
            </div>
            <div class="profile-tags">
              <span>ID {form.value.sso || "—"}</span>
              <span>{form.value.workline || "ยังไม่ได้ระบุสายงาน"}</span>
            </div>
          </div>
        </div>
        <div class="profile-actions">
          {isEditing.value ?
          <>
              <button class="btn btn-s" type="button" onClick={() => {
              const nextName = splitThaiName(user);
              setPhoto(user?.photo || "");
              setForm({
                sso: user?.sso || "",
                title: user?.t || "",
                firstName: nextName.firstName,
                lastName: nextName.lastName,
                firstNameEng: user?.fe || "",
                lastNameEng: user?.le || "",
                gender: user?.g || "",
                workline: user?.w || "",
                group: user?.d || "",
                position: user?.p || "",
                level: getDisplayLevel(user) || "",
                role: user?.r || "employee",
                evaluator1: user?.sup || "",
                evaluator2: user?.evaluator2 || "",
                active: user?.act !== false,
                email: user?.profileSaved ? user?.em || "" : "",
                phone: user?.profileSaved ? user?.ph || "" : "",
                affiliation: user?.profileAffiliation || ""
              });
              setIsDirty(false);
              setIsEditing(false);
            }}>ยกเลิก</button>
              <button class="btn btn-p" type="submit">บันทึกโปรไฟล์</button>
            </> :

          <button class="btn btn-p" type="button" onClick={() => {setIsDirty(false);setIsEditing(true);}}>แก้ไขโปรไฟล์</button>
          }
        </div>
      </section>

      <div class="profile-layout">
        <section class="profile-form">
          {!isEditing.value ?
          <>
              <div class="profile-section">
                <div class="fw8 fs14">ข้อมูลบุคลากร</div>
                <div class="profile-grid">
                  {detail("ID", form.value.sso)}
                  {detail("ชื่อ-นามสกุล", profileName)}
                  {detail("First Name", form.value.firstNameEng)}
                  {detail("Last Name", form.value.lastNameEng)}
                  {detail("เพศ", form.value.gender)}
                  {detail("อีเมล", form.value.email)}
                  {detail("เบอร์โทรศัพท์", form.value.phone)}
                  {detail("สังกัด/หน่วยงาน", form.value.affiliation)}
                </div>
              </div>
            </> :

          <>
              <div class="profile-section">
                <div class="fw8 fs14">ข้อมูลบุคลากร</div>
                <div class="profile-grid">
              <div class="fg">
                <label class="lbl">ID</label>
                <input class="inp profile-readonly" value={form.value.sso} readOnly />
              </div>
              <div class="fg">
                <label class="lbl">คำนำหน้า <span class="required">*</span></label>
                <input class="inp" value={form.value.title} onChange={(e) => setValue("title", e.target.value)} required />
              </div>
              <div class="fg">
                <label class="lbl">ชื่อ (ภาษาไทย) <span class="required">*</span></label>
                <input class="inp" value={form.value.firstName} onChange={(e) => setValue("firstName", e.target.value)} required />
              </div>
              <div class="fg">
                <label class="lbl">นามสกุล (ภาษาไทย) <span class="required">*</span></label>
                <input class="inp" value={form.value.lastName} onChange={(e) => setValue("lastName", e.target.value)} required />
              </div>
              <div class="fg">
                <label class="lbl">First Name <span class="required">*</span></label>
                <input class="inp" value={form.value.firstNameEng} onChange={(e) => setValue("firstNameEng", e.target.value)} required />
              </div>
              <div class="fg">
                <label class="lbl">Last Name <span class="required">*</span></label>
                <input class="inp" value={form.value.lastNameEng} onChange={(e) => setValue("lastNameEng", e.target.value)} required />
              </div>
              <div class="fg">
                <label class="lbl">เพศ <span class="required">*</span></label>
                <select class="sel" value={form.value.gender} onChange={(e) => setValue("gender", e.target.value)} required>
                  <option value="">— เลือกเพศ —</option>
                  <option value="ชาย">ชาย</option>
                  <option value="หญิง">หญิง</option>
                  <option value="อื่นๆ">อื่นๆ</option>
                </select>
              </div>
              <div class="fg">
                <label class="lbl">อีเมล</label>
                <input class="inp" type="email" value={form.value.email} onChange={(e) => setValue("email", e.target.value)} placeholder="-" />
              </div>
              <div class="fg">
                <label class="lbl">เบอร์โทรศัพท์</label>
                <input class="inp" value={form.value.phone} onChange={(e) => setValue("phone", e.target.value)} placeholder="-" />
              </div>
              <div class="fg">
                <label class="lbl">สังกัด/หน่วยงาน</label>
                <input class="inp" value={form.value.affiliation} onChange={(e) => setValue("affiliation", e.target.value)} placeholder="-" />
              </div>
            </div>
              </div>

            </>
          }
        </section>
      </div>

      <style>{`
        .profile-page { display: grid; gap: 14px; }
        .profile-banner { display: flex; align-items: center; justify-content: space-between; gap: 18px; min-height: 198px; padding: 26px; border: 1px solid var(--border); border-radius: var(--r-lg); background: #fff; }
        .profile-identity { min-width: 0; display: flex; align-items: center; gap: 20px; }
        .profile-title { min-width: 0; display: grid; gap: 5px; }
        .profile-name { color: var(--navy); font-size: 25px; font-weight: 850; line-height: 1.2; overflow-wrap: anywhere; }
        .profile-roleline { color: var(--text2); font-size: 13px; font-weight: 600; overflow-wrap: anywhere; }
        .profile-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; }
        .profile-tags span { min-height: 27px; display: inline-flex; align-items: center; padding: 0 9px; border: 1px solid var(--border); border-radius: 7px; background: var(--bg); color: var(--text2); font-size: 12px; font-weight: 700; }
        .profile-tags .ok { border-color: #bbf7d0; background: #f0fdf4; color: #15803d; }
        .profile-tags .off { border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
        .profile-actions { flex: 0 0 auto; display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; max-width: 330px; }
        .profile-photo-slot { display: grid; justify-items: center; gap: 8px; }
        .profile-photo-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; }
        .profile-avatar { width: 136px; height: 136px; display: flex; align-items: center; justify-content: center; border-radius: 50%; overflow: hidden; background: var(--navy); color: #fff; font-size: 44px; font-weight: 800; border: 6px solid var(--bg); }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-upload { position: relative; overflow: hidden; cursor: pointer; }
        .profile-upload input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .profile-layout { min-width: 0; }
        .profile-form { display: grid; gap: 12px; }
        .profile-section { padding: 20px; border: 1px solid var(--border); border-radius: var(--r-lg); background: #fff; }
        .profile-grid { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 12px; }
        .profile-detail { min-height: 66px; display: grid; align-content: center; gap: 5px; padding: 11px 13px; border-left: 3px solid var(--blue); border-radius: 0 var(--r) var(--r) 0; background: var(--bg); }
        .profile-detail-value { color: var(--text); font-size: 14px; font-weight: 700; overflow-wrap: anywhere; }
        .profile-readonly { border-color: #e2e8f0; background: #f1f5f9; color: var(--text3); cursor: not-allowed; }
        .required { color: var(--red); }
        @media (max-width: 760px) {
          .profile-banner, .profile-identity { align-items: flex-start; flex-direction: column; }
          .profile-banner { padding: 18px; }
          .profile-actions { width: 100%; justify-content: flex-start; }
          .profile-name { font-size: 21px; }
          .profile-avatar { width: 108px; height: 108px; font-size: 36px; }
          .profile-grid { grid-template-columns: 1fr; }
        }
      `}</style>
    </form>;} });



export default Profile;
</script>
