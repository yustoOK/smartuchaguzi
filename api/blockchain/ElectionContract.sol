// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0; 

contract ElectionContract {
    address public admin;
    bool public isActive;

    struct Position {
        string name;
        uint256 hostelId; // 0 for non-hostel positions
        uint256 collegeId;
    }

    struct Candidate {
        uint256 id;
        string name;
        uint256 positionId;
        uint256 assistantId; // 0 if no assistant
    }

    mapping(uint256 => Position) public positions;
    mapping(uint256 => Candidate) public candidates;
    mapping(uint256 => uint256[]) private _positionCandidates;
    uint256 public positionCount;
    uint256 public candidateCount;

    event PositionAdded(uint256 positionId, string name, uint256 hostelId, uint256 collegeId);
    event CandidateAdded(uint256 candidateId, string name, uint256 positionId);
    event ElectionStarted();
    event ElectionEnded();

    modifier onlyAdmin() {
        require(msg.sender == admin, "Only admin allowed");
        _;
    }

    constructor() {
        admin = msg.sender;
        isActive = false;
    }

    function addPosition(
        string memory name,
        uint256 hostelId,
        uint256 collegeId
    ) external onlyAdmin {
        positionCount++;
        positions[positionCount] = Position(name, hostelId, collegeId);
        emit PositionAdded(positionCount, name, hostelId, collegeId);
    }

    function addCandidate(
        string memory name,
        uint256 positionId,
        uint256 assistantId
    ) external onlyAdmin {
        require(positionId > 0 && positionId <= positionCount, "Invalid position");

        candidateCount++;
        candidates[candidateCount] = Candidate(candidateCount, name, positionId, assistantId);
        _positionCandidates[positionId].push(candidateCount);
        emit CandidateAdded(candidateCount, name, positionId);
    }

    function startElection() external onlyAdmin {
        isActive = true;
        emit ElectionStarted();
    }

    function endElection() external onlyAdmin {
        isActive = false;
        emit ElectionEnded();
    }

    function getPosition(uint256 id) external view returns (Position memory) {
        return positions[id];
    }

    // ✅ Adding a getter for candidates by position
    function getPositionCandidates(uint256 positionId) external view returns (uint256[] memory) {
        return _positionCandidates[positionId];
    }
}
