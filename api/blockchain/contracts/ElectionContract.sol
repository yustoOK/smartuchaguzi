// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract ElectionContract { 
    address public admin;
    uint256 public electionCount;

    struct Election {
        uint256 id;
        string name;
        bool isActive;
    }

    struct Position {
        uint256 id;
        uint256 electionId;
        string name;
        uint256 hostelId; // 0 for non-hostel positions
        uint256 collegeId;
    }

    struct Candidate {
        uint256 id;
        string name;
        uint256 positionId;
        uint256 electionId;
    }

    mapping(uint256 => Election) public elections;
    mapping(uint256 => Position) public positions;
    mapping(uint256 => Candidate) public candidates;
    mapping(uint256 => uint256[]) private positionCandidates; // positionId => candidateIds
    uint256 public positionCount;
    uint256 public candidateCount;

    event ElectionCreated(uint256 electionId, string name);
    event ElectionStarted(uint256 electionId);
    event ElectionEnded(uint256 electionId);
    event PositionAdded(uint256 positionId, uint256 electionId, string name, uint256 hostelId, uint256 collegeId);
    event CandidateAdded(uint256 candidateId, uint256 electionId, string name, uint256 positionId);

    modifier onlyAdmin() {
        require(msg.sender == admin, "Only admin allowed");
        _;
    }

    constructor() {
        admin = msg.sender;
        electionCount = 0;
    }

    function createElection(string memory name) external onlyAdmin {
        electionCount++;
        elections[electionCount] = Election(electionCount, name, false);
        emit ElectionCreated(electionCount, name);
    }

    function addPosition(
        uint256 electionId,
        string memory name,
        uint256 hostelId,
        uint256 collegeId
    ) external onlyAdmin {
        require(electionId > 0 && electionId <= electionCount, "Invalid election");
        positionCount++;
        positions[positionCount] = Position(positionCount, electionId, name, hostelId, collegeId);
        emit PositionAdded(positionCount, electionId, name, hostelId, collegeId);
    }

    function addCandidate(
        uint256 electionId,
        string memory name,
        uint256 positionId
    ) external onlyAdmin {
        require(electionId > 0 && electionId <= electionCount, "Invalid election");
        require(positionId > 0 && positionId <= positionCount, "Invalid position");
        require(positions[positionId].electionId == electionId, "Position not in election");

        candidateCount++;
        candidates[candidateCount] = Candidate(candidateCount, name, positionId, electionId);
        positionCandidates[positionId].push(candidateCount);
        emit CandidateAdded(candidateCount, electionId, name, positionId);
    }

    function startElection(uint256 electionId) external onlyAdmin {
        require(electionId > 0 && electionId <= electionCount, "Invalid election");
        elections[electionId].isActive = true;
        emit ElectionStarted(electionId);
    }

    function endElection(uint256 electionId) external onlyAdmin {
        require(electionId > 0 && electionId <= electionCount, "Invalid election");
        elections[electionId].isActive = false;
        emit ElectionEnded(electionId);
    }

    function getElection(uint256 electionId) external view returns (Election memory) {
        require(electionId > 0 && electionId <= electionCount, "Invalid election");
        return elections[electionId];
    }

    function getPosition(uint256 positionId) external view returns (Position memory) {
        require(positionId > 0 && positionId <= positionCount, "Invalid position");
        return positions[positionId];
    }

    function getPositionCandidates(uint256 positionId) external view returns (uint256[] memory) {
        require(positionId > 0 && positionId <= positionCount, "Invalid position");
        return positionCandidates[positionId];
    }

    function getElectionPositions(uint256 electionId) external view returns (uint256[] memory) {
        uint256[] memory result = new uint256[](positionCount);
        uint256 count = 0;
        for (uint256 i = 1; i <= positionCount; i++) {
            if (positions[i].electionId == electionId) {
                result[count] = i;
                count++;
            }
        }
        uint256[] memory trimmed = new uint256[](count);
        for (uint256 i = 0; i < count; i++) {
            trimmed[i] = result[i];
        }
        return trimmed;
    }
}