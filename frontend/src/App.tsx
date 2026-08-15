import { Navigate, Route, Routes } from "react-router-dom";
import { useAuth } from "./auth";
import { Shell } from "./layout/Shell";
import { Login } from "./pages/Login";
import { Dashboard } from "./pages/Dashboard";
import { Lab } from "./pages/Lab";
import { Machines } from "./pages/Machines";
import { Wizard } from "./pages/Wizard";
import { Templates } from "./pages/Templates";
import { Images } from "./pages/Images";
import { Networks } from "./pages/Networks";
import { Topology } from "./pages/Topology";
import { Terminal } from "./pages/Terminal";
import { Console } from "./pages/Console";
import { Exercises } from "./pages/Exercises";
import { Resources } from "./pages/Resources";
import { Activity } from "./pages/Activity";
import { Settings } from "./pages/Settings";
import { AdminHome } from "./pages/admin/AdminHome";
import { AdminPeople } from "./pages/admin/AdminPeople";
import { AdminInfra } from "./pages/admin/AdminInfra";
import { AdminOps } from "./pages/admin/AdminOps";

function Guard({ children }: { children: React.ReactNode }) {
  const { user, loading } = useAuth();
  if (loading) {
    return (
      <div className="grid min-h-screen place-items-center text-slate-400">
        Restoring session…
      </div>
    );
  }
  if (!user) return <Navigate to="/login" replace />;
  return <>{children}</>;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route
        path="/"
        element={
          <Guard>
            <Shell />
          </Guard>
        }
      >
        <Route index element={<Dashboard />} />
        <Route path="lab" element={<Lab />} />
        <Route path="machines" element={<Machines />} />
        <Route path="machines/create" element={<Wizard />} />
        <Route path="templates" element={<Templates />} />
        <Route path="images" element={<Images />} />
        <Route path="networks" element={<Networks />} />
        <Route path="topology" element={<Topology />} />
        <Route path="terminal/:id" element={<Terminal />} />
        <Route path="console/:id" element={<Console />} />
        <Route path="exercises" element={<Exercises />} />
        <Route path="resources" element={<Resources />} />
        <Route path="activity" element={<Activity />} />
        <Route path="settings" element={<Settings />} />
        <Route path="admin" element={<AdminHome />} />
        <Route path="admin/people" element={<AdminPeople />} />
        <Route path="admin/infra" element={<AdminInfra />} />
        <Route path="admin/ops" element={<AdminOps />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
