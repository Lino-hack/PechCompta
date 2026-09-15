import { useEffect, useState } from 'react'
import { Outlet, NavLink, useNavigate } from 'react-router-dom'
import { Home, Banknote, Truck, Sun, Moon, LogOut, BarChart3, Settings, Fish } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'

function ThemeToggle() {
  const [dark, setDark] = useState(() => {
    if (typeof window === 'undefined') return false
    const stored = localStorage.getItem('peche_theme')
    if (stored !== null) return stored === 'dark'
    return window.matchMedia('(prefers-color-scheme: dark)').matches
  })

  useEffect(() => {
    document.documentElement.classList.toggle('dark', dark)
    localStorage.setItem('peche_theme', dark ? 'dark' : 'light')
  }, [dark])

  return (
    <button
      onClick={() => setDark((value) => !value)}
      className="p-2 rounded-lg text-slate-500 hover:text-sky-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-sky-300 dark:hover:bg-slate-800 transition-colors"
      aria-label="Basculer le thème"
    >
      {dark ? <Sun size={20} /> : <Moon size={20} />}
    </button>
  )
}

export default function AppLayout() {
  const { logout, user } = useAuth()
  const navigate = useNavigate()

  function handleLogout() {
    logout()
    navigate('/login', { replace: true })
  }

  return (
    <div className="flex flex-col min-h-screen bg-slate-50 dark:bg-slate-950 pb-20 sm:pb-0 sm:flex-row">
      <aside className="hidden sm:flex sm:flex-col w-64 border-r border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
        <div className="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center gap-2">
          <div className="h-8 w-8 rounded-lg bg-gradient-to-br from-sky-400 to-sky-700 flex items-center justify-center">
            <Fish className="h-4 w-4 text-white" />
          </div>
          <h1 className="text-lg font-bold text-slate-900 dark:text-white">PêchCompta</h1>
        </div>
        <nav className="flex-1 p-4 space-y-1">
          <NavItem to="/dashboard" icon={<Home size={20} />} label="Journée" />
          <NavItem to="/charges" icon={<Banknote size={20} />} label="Charges" />
          <NavItem to="/cycles" icon={<Truck size={20} />} label="Cycles" />
          <NavItem to="/stats" icon={<BarChart3 size={20} />} label="Statistiques" />
          <NavItem to="/settings" icon={<Settings size={20} />} label="Réglages" />
        </nav>
        <div className="border-t border-slate-200 dark:border-slate-800 p-4 space-y-3">
          <div className="flex items-center justify-between">
            <div className="min-w-0">
              <p className="text-sm font-medium text-slate-700 dark:text-slate-200 truncate">{user?.name}</p>
              <p className="text-xs text-slate-400 truncate">{user?.email}</p>
            </div>
            <ThemeToggle />
          </div>
          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-slate-600 hover:bg-red-50 hover:text-red-600 dark:text-slate-400 dark:hover:bg-red-950/50 dark:hover:text-red-400 transition-colors"
          >
            <LogOut size={18} />
            Se déconnecter
          </button>
        </div>
      </aside>

      <main className="flex-1 overflow-auto">
        <div className="p-4 sm:p-6 mx-auto max-w-5xl">
          <Outlet />
        </div>
      </main>

      <nav className="sm:hidden fixed bottom-0 left-0 right-0 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex justify-around p-2 pb-safe">
        <MobileNavItem to="/dashboard" icon={<Home size={24} />} label="Journée" />
        <MobileNavItem to="/charges" icon={<Banknote size={24} />} label="Charges" />
        <MobileNavItem to="/cycles" icon={<Truck size={24} />} label="Cycles" />
        <MobileNavItem to="/stats" icon={<BarChart3 size={24} />} label="Stats" />
        <MobileNavItem to="/settings" icon={<Settings size={24} />} label="Réglages" />
      </nav>
    </div>
  )
}

function NavItem({ to, icon, label }: { to: string, icon: React.ReactNode, label: string }) {
  return (
    <NavLink
      to={to}
      className={({ isActive }) =>
        `flex items-center gap-3 px-3 py-2 rounded-md transition-colors ${
          isActive
            ? 'bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300'
            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
        }`
      }
    >
      {icon}
      <span className="font-medium">{label}</span>
    </NavLink>
  )
}

function MobileNavItem({ to, icon, label }: { to: string, icon: React.ReactNode, label: string }) {
  return (
    <NavLink
      to={to}
      className={({ isActive }) =>
        `flex flex-col items-center p-2 rounded-lg ${
          isActive ? 'text-sky-600 dark:text-sky-400' : 'text-slate-500 dark:text-slate-400'
        }`
      }
    >
      {icon}
      <span className="text-[10px] mt-1 font-medium">{label}</span>
    </NavLink>
  )
}