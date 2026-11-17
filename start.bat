@echo off
title Anza Auto Driving School Management System
echo ========================================================
echo  ANZA AUTO DRIVING SCHOOL MANAGEMENT SYSTEM - MOMBASA BRANCH
echo  Academic Research Project - Cynthia Jeruto (KeMU)
echo ========================================================
echo.
echo Starting system server...
cd /d "%~dp0node"
start "" "http://localhost:3000"
node server.js
pause
